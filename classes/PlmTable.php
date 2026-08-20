<?php

class PlmTable
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmParser */
    private $parser;

    /** @var PlmState */
    private $state;

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct,
        PlmParser $parser,
        PlmState $state
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
        $this->parser = $parser;
        $this->state = $state;
    }

    public function render(
        string $name,
        string $schema,
        string $filter,
        string $content,
        string $errortext = 'not found!'
    ): void {

        if (empty($schema)) {
            $this->error(
                'PLM table: parameter "schema" is required'
            );

            return;
        }

        $params =
            $this->parser->parse(
                $content
            );

        if (empty($params['cols'])) {
            $this->error(
                'PLM table: parameter "cols" is required'
            );

            return;
        }

        $columns =
            $params['cols'];

        try {

            $fields =
                $this->getStructFields(
                    $params
                );

            $filter =
                $this->expandFilter(
                    $filter
                );

            $stateFilter =
                $this->buildStateFilter(
                    $name
                );

            $filter =
                $this->combineFilters(
                    $filter,
                    $stateFilter
                );

            /*
             * Extract special Lookup RID filters.
             *
             * Example:
             *
             *     part._pk=123
             *
             * The expression is NOT sent to Struct.
             */
            $lookupFilters =
                $this->extractLookupFilters(
                    $filter
                );

            $structFilter =
                $lookupFilters['filter'];

            /*
             * The lookup field itself must be included
             * in the result so we can inspect its stored
             * [page-id,rid] value.
             */
            foreach (
                $lookupFilters['fields']
                as $lookupField
            ) {

                if (!in_array(
                    $lookupField,
                    $fields,
                    true
                )) {

                    $fields[] =
                        $lookupField;
                }
            }

            /*
             * Remove accidental *. _pk fields.
             *
             * A Lookup RID is a PLM-level pseudo field and
             * must never be passed to Struct.
             *
             * Example:
             *
             *     part._pk
             *
             * becomes:
             *
             *     part
             */
            $fields =
                $this->normalizeStructFields(
                    $fields
                );

            $result =
                $this->struct->search(
                    $schema,
                    $fields,
                    $structFilter
                );

            $search =
                $result['search'];

            $rows =
                $result['rows'];

            /*
             * Apply Lookup RID filters in PHP.
             *
             * This is deliberately done after Struct
             * has returned the matching records.
             */
            if (!empty($lookupFilters['conditions'])) {

                [
                    $search,
                    $rows
                ] =
                    $this->filterRowsByLookupRid(
                        $search,
                        $rows,
                        $lookupFilters['conditions']
                    );
            }

        } catch (Throwable $e) {

            $this->error(
                'PLM table: ' .
                $e->getMessage()
            );

            return;
        }

        $this->renderTable(
            $name,
            $schema,
            $columns,
            $search,
            $rows,
            $params,
            $errortext
        );
    }

    /**
     * Normalize PLM pseudo fields before passing them
     * to Struct.
     *
     * Exact "_pk" is handled by PlmStruct itself.
     *
     * Lookup pseudo fields:
     *
     *     part._pk
     *
     * are converted to:
     *
     *     part
     */
    private function normalizeStructFields(
        array $fields
    ): array {

        $result = [];

        foreach ($fields as $field) {

            $field =
                trim(
                    (string) $field
                );

            if ($field === '') {
                continue;
            }

            if (
                $field !==
                PlmStruct::PRIMARY_KEY_FIELD &&
                str_ends_with(
                    $field,
                    '._pk'
                )
            ) {

                $field =
                    substr(
                        $field,
                        0,
                        -4
                    );
            }

            if ($field === '') {
                continue;
            }

            $result[] =
                $field;
        }

        return array_values(
            array_unique(
                $result
            )
        );
    }

    /**
     * Expand PLM filter placeholders.
     *
     * Supports:
     *
     *     &_pk
     *
     * and:
     *
     *     $partselect._pk
     */
    private function expandFilter(
        string $filter
    ): ?string {

        if ($filter === '') {
            return null;
        }

        $hasEmptyParameter = false;

        /*
         * URI parameters.
         */
        $filter =
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match)
                    use (&$hasEmptyParameter) {

                    $value =
                        $this->getUriParam(
                            $match[1]
                        );

                    if ($value === '') {
                        $hasEmptyParameter = true;
                    }

                    return $value;
                },
                $filter
            );

        /*
         * PLM select references.
         *
         *     $partselect._pk
         */
        $filter =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_-]+)\._pk\b/',
                function ($match)
                    use (&$hasEmptyParameter) {

                    $selectName =
                        $match[1];

                    $value =
                        PlmSelect::getSelectionValue(
                            $selectName,
                            PlmStruct::PRIMARY_KEY_FIELD
                        );

                    if (
                        $value === null ||
                        $value === ''
                    ) {

                        $hasEmptyParameter = true;

                        return '';
                    }

                    if (
                        !ctype_digit(
                            (string) $value
                        ) ||
                        (int) $value <= 0
                    ) {

                        throw new \RuntimeException(
                            'PLM select "' .
                            $selectName .
                            '" returned an invalid _pk.'
                        );
                    }

                    return (string) $value;
                },
                $filter
            );

        if ($hasEmptyParameter) {
            return null;
        }

        return trim($filter);
    }

    /**
     * Extract Lookup RID filters from a PLM filter.
     *
     * Recognized syntax:
     *
     *     part._pk=123
     *     part._pk = 123
     *
     * Returns:
     *
     *     [
     *         'filter' => normal Struct filter or null,
     *         'conditions' => [
     *             [
     *                 'field' => 'part',
     *                 'rid' => 123,
     *                 'operator' => '='
     *             ]
     *         ],
     *         'fields' => ['part']
     *     ]
     */
    private function extractLookupFilters(
        ?string $filter
    ): array {

        if (
            $filter === null ||
            trim($filter) === ''
        ) {

            return [
                'filter' =>
                    $filter,

                'conditions' =>
                    [],

                'fields' =>
                    [],
            ];
        }

        $conditions = [];
        $fields = [];

        /*
         * Only simple equality/inequality is supported
         * for Lookup._pk.
         */
        $pattern =
            '/\b(' .
            '[a-zA-Z0-9_.-]+' .
            ')\s*' .
            '(!=|=)' .
            '\s*' .
            '([0-9]+)' .
            '\b/';

        $normalFilter =
            preg_replace_callback(
                $pattern,
                function ($match) use (
                    &$conditions,
                    &$fields
                ) {

                    $field =
                        $match[1];

                    $operator =
                        $match[2];

                    $rid =
                        (int) $match[3];

                    /*
                     * Only field._pk is special.
                     */
                    if (
                        !str_ends_with(
                            $field,
                            '._pk'
                        )
                    ) {
                        return $match[0];
                    }

                    $lookupField =
                        substr(
                            $field,
                            0,
                            -4
                        );

                    if ($lookupField === '') {
                        throw new \RuntimeException(
                            'Invalid PLM Lookup _pk filter.'
                        );
                    }

                    if ($rid <= 0) {
                        throw new \RuntimeException(
                            'PLM Lookup _pk must be greater than zero.'
                        );
                    }

                    $conditions[] = [
                        'field' =>
                            $lookupField,

                        'rid' =>
                            $rid,

                        'operator' =>
                            $operator,
                    ];

                    $fields[] =
                        $lookupField;

                    return '';
                },
                $filter
            );

        /*
         * Clean up logical operators left behind after
         * removing special conditions.
         */
        $normalFilter =
            $this->cleanLogicalFilter(
                $normalFilter
            );

        return [
            'filter' =>
                $normalFilter,

            'conditions' =>
                $conditions,

            'fields' =>
                array_values(
                    array_unique(
                        $fields
                    )
                ),
        ];
    }

    /**
     * Remove logical debris left after special filter
     * conditions have been extracted.
     */
    private function cleanLogicalFilter(
        ?string $filter
    ): ?string {

        if ($filter === null) {
            return null;
        }

        $filter =
            trim(
                $filter
            );

        /*
         * Repeatedly remove empty parenthesized parts.
         */
        do {

            $oldFilter =
                $filter;

            $filter =
                preg_replace(
                    '/\(\s*\)/',
                    '',
                    $filter
                );

            $filter =
                preg_replace(
                    '/^\s*(AND|OR)\s+/i',
                    '',
                    $filter
                );

            $filter =
                preg_replace(
                    '/\s+(AND|OR)\s*$/i',
                    '',
                    $filter
                );

            $filter =
                preg_replace(
                    '/\(\s*(AND|OR)\s+/i',
                    '(',
                    $filter
                );

            $filter =
                preg_replace(
                    '/\s+(AND|OR)\s*\)/i',
                    ')',
                    $filter
                );

            $filter =
                trim(
                    $filter
                );

        } while (
            $oldFilter !== $filter
        );

        if ($filter === '') {
            return null;
        }

        /*
         * If only one wrapping pair remains, remove it.
         */
        while (
            strlen($filter) >= 2 &&
            $filter[0] === '(' &&
            $filter[strlen($filter) - 1] === ')'
        ) {

            if (
                !$this->hasMatchingOuterParentheses(
                    $filter
                )
            ) {
                break;
            }

            $filter =
                trim(
                    substr(
                        $filter,
                        1,
                        -1
                    )
                );
        }

        return $filter === ''
            ? null
            : $filter;
    }

    /**
     * Filter Struct rows according to Lookup RIDs.
     *
     * The Struct Lookup value internally contains:
     *
     *     [page-id, rid]
     *
     * We compare the rid, not the display value.
     *
     * Important:
     *
     * The Lookup type is determined from the Struct
     * Column, not from the Value object.
     */
    private function filterRowsByLookupRid(
        $search,
        array $rows,
        array $conditions
    ): array {

        if (empty($conditions)) {
            return [
                $search,
                $rows
            ];
        }

        /*
         * Build the column index and retain the actual
         * Struct Column objects.
         */
        $columns = [];

        foreach (
            $search->getColumns()
            as $index => $column
        ) {

            $columns[
                $column->getLabel()
            ] = [
                'index' =>
                    $index,

                'column' =>
                    $column,
            ];
        }

        $filteredRows = [];
        $filteredRids = [];
        $filteredPids = [];

        $rids =
            $search->getRids();

        $pids =
            $search->getPids();

        foreach (
            $rows as $rowIndex => $row
        ) {

            $matches = true;

            foreach (
                $conditions as $condition
            ) {

                $field =
                    $condition['field'];

                if (
                    !isset(
                        $columns[$field]
                    )
                ) {

                    throw new \RuntimeException(
                        'Lookup field "' .
                        $field .
                        '" was not returned by Struct.'
                    );
                }

                $column =
                    $columns[$field]['column'];

                $columnIndex =
                    $columns[$field]['index'];

                /*
                 * Determine Lookup from the Struct
                 * Column itself.
                 */
                if (
                    !$this->struct->isLookupColumn(
                        $column
                    )
                ) {

                    throw new \RuntimeException(
                        'Field "' .
                        $field .
                        '" is not a Struct Lookup field.'
                    );
                }

                if (
                    !array_key_exists(
                        $columnIndex,
                        $row
                    )
                ) {

                    $matches = false;
                    break;
                }

                $value =
                    $row[$columnIndex];

                /*
                 * Extract the actual referenced RID(s).
                 *
                 * PlmStruct handles the Struct Lookup JSON,
                 * including the case where getValue() returns
                 * a JSON encoded array containing JSON strings.
                 */
                $lookupRids =
                    $this->struct->getLookupPrimaryKeys(
                        $value
                    );

                $wantedRid =
                    (int) $condition['rid'];

                $contains =
                    in_array(
                        $wantedRid,
                        $lookupRids,
                        true
                    );

                if (
                    $condition['operator'] === '=' &&
                    !$contains
                ) {

                    $matches = false;
                    break;
                }

                if (
                    $condition['operator'] === '!=' &&
                    $contains
                ) {

                    $matches = false;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $filteredRows[] =
                $row;

            $filteredRids[] =
                $rids[$rowIndex] ?? 0;

            $filteredPids[] =
                $pids[$rowIndex] ?? '';
        }

        /*
         * SearchConfig itself cannot be reconstructed from
         * an already filtered result. We therefore wrap the
         * result object only for the methods PlmTable needs.
         */
        $filteredSearch =
            new PlmTableFilteredSearch(
                $search,
                $filteredPids,
                $filteredRids
            );

        return [
            $filteredSearch,
            $filteredRows,
        ];
    }

    /**
     * Build a Struct filter from current PLM state.
     */
    private function buildStateFilter(
        string $name
    ): ?string {

        $values =
            $this->state->getFilter(
                $name
            );

        if (empty($values)) {
            return null;
        }

        $parts = [];

        foreach (
            $values as $field => $value
        ) {

            if ($value === '') {
                continue;
            }

            $value =
                $this->escapeFilterValue(
                    (string) $value
                );

            $parts[] =
                $field .
                '~*' .
                $value .
                '*';
        }

        if (empty($parts)) {
            return null;
        }

        return implode(
            ' AND ',
            $parts
        );
    }

    /**
     * Escape characters with a special meaning
     * in Struct filter values.
     */
    private function escapeFilterValue(
        string $value
    ): string {

        return str_replace(
            [
                '\\',
                '*',
                '~',
                '[',
                ']',
            ],
            [
                '\\\\',
                '\\*',
                '\\~',
                '\\[',
                '\\]',
            ],
            $value
        );
    }

    /**
     * Combine explicit and state filters.
     */
    private function combineFilters(
        ?string $explicit,
        ?string $state
    ): ?string {

        if ($explicit === null) {
            return $state;
        }

        if ($state === null) {
            return $explicit;
        }

        return
            '(' .
            $explicit .
            ') AND (' .
            $state .
            ')';
    }

    /**
     * Determine all Struct fields required by the table.
     */
    private function getStructFields(
        array $params
    ): array {

        $fields = [];

        foreach (
            $params['cols'] ?? []
            as $col
        ) {

            if (
                str_starts_with(
                    $col,
                    '@'
                )
            ) {
                continue;
            }

            if (
                $col ===
                PlmStruct::PRIMARY_KEY_FIELD
            ) {
                continue;
            }

            $fields[] =
                $col;
        }

        foreach (
            $this->getConfiguredFields(
                $params['filter'] ?? []
            )
            as $field
        ) {

            if (
                $field !==
                PlmStruct::PRIMARY_KEY_FIELD
            ) {

                $fields[] =
                    $field;
            }
        }

        foreach (
            $this->getConfiguredFields(
                $params['create'] ?? []
            )
            as $field
        ) {

            if (
                $field !==
                PlmStruct::PRIMARY_KEY_FIELD
            ) {

                $fields[] =
                    $field;
            }
        }

        if (
            isset($params['delete']) &&
            is_string($params['delete'])
        ) {

            if (
                $params['delete'] !==
                PlmStruct::PRIMARY_KEY_FIELD
            ) {

                $fields[] =
                    $params['delete'];
            }
        }

        foreach (
            $params['template'] ?? []
            as $token
        ) {

            preg_match_all(
                '/\$([a-zA-Z0-9_.-]+)/',
                $token,
                $matches
            );

            foreach (
                $matches[1] ?? []
                as $field
            ) {

                if (
                    $field ===
                    PlmStruct::PRIMARY_KEY_FIELD
                ) {
                    continue;
                }

                $fields[] =
                    $field;
            }
        }

        return $this->normalizeStructFields(
            array_values(
                array_unique(
                    $fields
                )
            )
        );
    }

    /**
     * Get a URI parameter.
     */
    private function getUriParam(
        string $name
    ): string {

        global $INPUT;

        return
            $INPUT->str($name) ?? '';
    }

    /**
     * Get the configured template definition.
     */
    private function getTemplateDefinition(
        array $template,
        string $name
    ): ?array {

        if (
            empty($template) ||
            ($template[0] ?? null) !== $name
        ) {
            return null;
        }

        return [
            'name' =>
                $name,

            'label' =>
                $template[1] ??
                $name,

            'text' =>
                implode(
                    ' ',
                    array_slice(
                        $template,
                        2
                    )
                ),
        ];
    }

    /**
     * Expand a named PLM template.
     */
    private function expandTemplate(
        array $template,
        string $name,
        array $row,
        array $fieldIndexes,
        ?int $rid = null
    ): ?string {

        $definition =
            $this->getTemplateDefinition(
                $template,
                $name
            );

        if ($definition === null) {
            return null;
        }

        $text =
            $definition['text'];

        $text =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_.-]+)/',
                function ($match)
                    use (
                        $row,
                        $fieldIndexes,
                        $rid
                    ) {

                    $field =
                        $match[1];

                    if (
                        $field ===
                        PlmStruct::PRIMARY_KEY_FIELD
                    ) {

                        if ($rid === null) {
                            return $match[0];
                        }

                        return (string) $rid;
                    }

                    /*
                     * Lookup._pk inside templates.
                     */
                    if (
                        str_ends_with(
                            $field,
                            '._pk'
                        )
                    ) {

                        $lookupField =
                            substr(
                                $field,
                                0,
                                -4
                            );

                        if (
                            !isset(
                                $fieldIndexes[$lookupField]
                            )
                        ) {
                            return $match[0];
                        }

                        $lookupRids =
                            $this->struct->getLookupPrimaryKeys(
                                $row[
                                    $fieldIndexes[$lookupField]
                                ]
                            );

                        if (empty($lookupRids)) {
                            return '';
                        }

                        return implode(
                            ',',
                            $lookupRids
                        );
                    }

                    if (
                        !isset(
                            $fieldIndexes[$field]
                        )
                    ) {
                        return $match[0];
                    }

                    return $row[
                        $fieldIndexes[$field]
                    ]->getDisplayValue();
                },
                $text
            );

        $text =
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match) {

                    return $this->getUriParam(
                        $match[1]
                    );
                },
                $text
            );

        return $text;
    }

    /**
     * Render the complete table.
     */
    private function renderTable(
        string $name,
        string $schema,
        array $columns,
        $search,
        array $rows,
        array $params,
        string $errortext
    ): void {

        $fieldIndexes = [];
        $fieldLabels = [];

        foreach (
            $search->getColumns()
            as $index => $column
        ) {

            $field =
                $column->getLabel();

            $fieldIndexes[$field] =
                $index;

            $fieldLabels[$field] =
                $column->getTranslatedLabel();
        }

        $filterFields =
            $this->getFilterFields(
                $params
            );

        $createFields =
            $this->getCreateFields(
                $params
            );

        $deleteField =
            $this->getDeleteField(
                $params
            );

        $hasActions =
            !empty($filterFields) ||
            !empty($createFields) ||
            $deleteField !== null;

        if ($hasActions) {

            $this->renderer->doc .=
                '<form method="post" ' .
                'class="plm_table_form" ' .
                'onkeydown="' .
                'if(event.key===\'Enter\' && ' .
                'event.target.tagName!==\'BUTTON\'){' .
                'event.preventDefault();' .
                'event.stopPropagation();' .
                'return false;' .
                '}' .
                '">' .
                '<input type="hidden" ' .
                'name="plm_form_submit" ' .
                'value="1">' .
                '<input type="hidden" ' .
                'name="plm_table" ' .
                'value="' .
                hsc($name) .
                '">' .
                '<input type="hidden" ' .
                'name="plm_schema" ' .
                'value="' .
                hsc($schema) .
                '">' .
                '<input type="hidden" ' .
                'name="sectok" ' .
                'value="' .
                hsc(
                    getSecurityToken()
                ) .
                '">';
        }

        $this->renderer->table_open();

        $this->renderer->tablerow_open();

        foreach ($columns as $column) {

            $this->renderer->tableheader_open();

            if (
                str_starts_with(
                    $column,
                    '@'
                )
            ) {

                $templateName =
                    substr(
                        $column,
                        1
                    );

                $definition =
                    $this->getTemplateDefinition(
                        $params['template'] ?? [],
                        $templateName
                    );

                $this->renderer->cdata(
                    $definition['label']
                        ?? $templateName
                );

            } elseif (
                $column ===
                PlmStruct::PRIMARY_KEY_FIELD
            ) {

                $this->renderer->cdata(
                    PlmStruct::PRIMARY_KEY_FIELD
                );

            } else {

                $this->renderer->cdata(
                    $fieldLabels[$column]
                        ?? $column
                );
            }

            $this->renderer->tableheader_close();
        }

        if ($hasActions) {

            $this->renderer->tableheader_open();

            $this->renderer->cdata(
                'Action'
            );

            $this->renderer->tableheader_close();
        }

        $this->renderer->tablerow_close();

        if (!empty($createFields)) {

            $this->renderCreateRow(
                $columns,
                $createFields
            );
        }

        if (!empty($filterFields)) {

            $this->renderFilterRow(
                $name,
                $columns,
                $filterFields
            );
        }

        $rids =
            $search->getRids();

        foreach ($rows as $rowIndex => $row) {

            $rid =
                (int) (
                    $rids[$rowIndex] ?? 0
                );

            $this->renderer->tablerow_open();

            foreach ($columns as $column) {

                $this->renderer->tablecell_open();

                if (
                    str_starts_with(
                        $column,
                        '@'
                    )
                ) {

                    $templateName =
                        substr(
                            $column,
                            1
                        );

                    $expanded =
                        $this->expandTemplate(
                            $params['template'] ?? [],
                            $templateName,
                            $row,
                            $fieldIndexes,
                            $rid
                        );

                    if ($expanded !== null) {

                        $instructions =
                            p_get_instructions(
                                $expanded
                            );

                        $info = [];

                        $html =
                            p_render(
                                'xhtml',
                                $instructions,
                                $info
                            );

                        $this->renderer->doc .=
                            $html;
                    }

                } elseif (
                    $column ===
                    PlmStruct::PRIMARY_KEY_FIELD
                ) {

                    $this->renderer->cdata(
                        (string) $rid
                    );

                } elseif (
                    isset(
                        $fieldIndexes[$column]
                    )
                ) {

                    $row[
                        $fieldIndexes[$column]
                    ]->render(
                        $this->renderer,
                        'xhtml'
                    );
                }

                $this->renderer->tablecell_close();
            }

            if ($hasActions) {

                $this->renderer->tablecell_open();

                if ($deleteField !== null) {

                    $deleteValue =
                        '';

                    if (
                        $deleteField ===
                        PlmStruct::PRIMARY_KEY_FIELD
                    ) {

                        $deleteValue =
                            (string) $rid;

                    } elseif (
                        isset(
                            $fieldIndexes[$deleteField]
                        )
                    ) {

                        $deleteValue =
                            $row[
                                $fieldIndexes[$deleteField]
                            ]->getDisplayValue();
                    }

                    if ($deleteValue !== '') {

                        $this->renderer->doc .=
                            '<button type="submit" ' .
                            'name="plm_action" ' .
                            'value="delete" ' .
                            'class="plm_table_delete_button">' .
                            hsc('Delete') .
                            '</button>' .
                            '<input type="hidden" ' .
                            'name="plm_delete[' .
                            hsc($deleteField) .
                            ']" ' .
                            'value="' .
                            hsc($deleteValue) .
                            '">';
                    }
                }

                $this->renderer->tablecell_close();
            }

            $this->renderer->tablerow_close();
        }

        if (empty($rows)) {

            $this->renderer->tablerow_open();

            $this->renderer->tablecell_open();

            $this->renderer->doc .=
                '<div class="plm_empty">' .
                hsc($errortext) .
                '</div>';

            $this->renderer->tablecell_close();

            for (
                $i = 1;
                $i < count($columns);
                $i++
            ) {

                $this->renderer->tablecell_open();
                $this->renderer->tablecell_close();
            }

            if ($hasActions) {

                $this->renderer->tablecell_open();
                $this->renderer->tablecell_close();
            }

            $this->renderer->tablerow_close();
        }

        $this->renderer->table_close();

        if ($hasActions) {

            $this->renderer->doc .=
                '</form>';
        }
    }

    private function getFilterFields(
        array $params
    ): array {

        return $this->getConfiguredFields(
            $params['filter'] ?? []
        );
    }

    private function getCreateFields(
        array $params
    ): array {

        return $this->getConfiguredFields(
            $params['create'] ?? []
        );
    }

    private function getDeleteField(
        array $params
    ): ?string {

        if (
            !isset($params['delete']) ||
            !is_string($params['delete'])
        ) {
            return null;
        }

        $field =
            trim(
                $params['delete']
            );

        if ($field === '') {
            return null;
        }

        if (
            $field !==
            PlmStruct::PRIMARY_KEY_FIELD &&
            !preg_match(
                '/^[a-zA-Z0-9_.-]+$/',
                $field
            )
        ) {
            return null;
        }

        return $field;
    }

    private function getConfiguredFields(
        array $fields
    ): array {

        $result = [];

        foreach ($fields as $field) {

            $parts =
                preg_split(
                    '/\s*,\s*/',
                    (string) $field
                );

            foreach ($parts as $part) {

                $part =
                    trim(
                        $part
                    );

                if ($part === '') {
                    continue;
                }

                if (
                    $part !==
                    PlmStruct::PRIMARY_KEY_FIELD &&
                    !preg_match(
                        '/^[a-zA-Z0-9_.-]+$/',
                        $part
                    )
                ) {
                    continue;
                }

                $result[] =
                    $part;
            }
        }

        return array_values(
            array_unique(
                $result
            )
        );
    }

    private function renderCreateRow(
        array $columns,
        array $createFields
    ): void {

        $this->renderer->tablerow_open();

        foreach ($columns as $column) {

            $this->renderer->tablecell_open();

            if (
                str_starts_with(
                    $column,
                    '@'
                ) ||
                $column ===
                    PlmStruct::PRIMARY_KEY_FIELD ||
                !in_array(
                    $column,
                    $createFields,
                    true
                )
            ) {

                $this->renderer->tablecell_close();
                continue;
            }

            $this->renderer->doc .=
                '<input type="text" ' .
                'name="plm_create[' .
                hsc($column) .
                ']" ' .
                'value="" ' .
                'placeholder="' .
                hsc(
                    'Create ' . $column
                ) .
                '" ' .
                'onkeydown="' .
                'if(event.key===\'Enter\'){' .
                'event.preventDefault();' .
                'event.stopPropagation();' .
                'return false;' .
                '}' .
                '">';

            $this->renderer->tablecell_close();
        }

        $this->renderer->tablecell_open();

        $this->renderer->doc .=
            '<button type="submit" ' .
            'name="plm_action" ' .
            'value="create" ' .
            'class="plm_table_create_button">' .
            hsc('Create') .
            '</button>';

        $this->renderer->tablecell_close();

        $this->renderer->tablerow_close();
    }

    private function renderFilterRow(
        string $name,
        array $columns,
        array $filterFields
    ): void {

        $this->renderer->tablerow_open();

        foreach ($columns as $column) {

            $this->renderer->tablecell_open();

            if (
                str_starts_with(
                    $column,
                    '@'
                ) ||
                $column ===
                    PlmStruct::PRIMARY_KEY_FIELD ||
                !in_array(
                    $column,
                    $filterFields,
                    true
                )
            ) {

                $this->renderer->tablecell_close();
                continue;
            }

            $value =
                $this->state->getFilterValue(
                    $name,
                    $column
                );

            $this->renderer->doc .=
                '<input type="text" ' .
                'name="plm_filter[' .
                hsc($column) .
                ']" ' .
                'value="' .
                hsc($value) .
                '" ' .
                'placeholder="' .
                hsc(
                    'Filter ' . $column
                ) .
                '" ' .
                'onkeydown="' .
                'if(event.key===\'Enter\'){' .
                'event.preventDefault();' .
                'event.stopPropagation();' .
                'return false;' .
                '}' .
                '">';

            $this->renderer->tablecell_close();
        }

        $this->renderer->tablecell_open();

        $this->renderer->doc .=
            '<button type="submit" ' .
            'name="plm_action" ' .
            'value="filter" ' .
            'class="plm_table_filter_button">' .
            hsc('Filter') .
            '</button>';

        $this->renderer->tablecell_close();

        $this->renderer->tablerow_close();
    }

    private function error(
        string $message
    ): void {

        $this->renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }

    /**
     * Check whether an outer pair of parentheses
     * encloses the complete expression.
     */
    private function hasMatchingOuterParentheses(
        string $value
    ): bool {

        $depth = 0;
        $length = strlen($value);

        for (
            $i = 0;
            $i < $length;
            $i++
        ) {

            if ($value[$i] === '(') {
                $depth++;
            } elseif ($value[$i] === ')') {

                $depth--;

                if (
                    $depth === 0 &&
                    $i < $length - 1
                ) {
                    return false;
                }
            }

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }
}


/**
 * Lightweight wrapper around a Struct Search object
 * with a filtered subset of rows/RIDs/PIDs.
 *
 * PlmTable only needs these methods after its
 * application-level Lookup filtering.
 */
class PlmTableFilteredSearch
{
    private $search;

    private array $pids;

    private array $rids;

    public function __construct(
        $search,
        array $pids,
        array $rids
    ) {
        $this->search = $search;
        $this->pids = $pids;
        $this->rids = $rids;
    }

    public function getColumns()
    {
        return
            $this->search->getColumns();
    }

    public function getPids(): array
    {
        return $this->pids;
    }

    public function getRids(): array
    {
        return $this->rids;
    }
}