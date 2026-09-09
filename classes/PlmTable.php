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

    /** @var PlmReference */
    private $reference;

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

        $this->reference =
            new PlmReference(
                $state,
                $struct
            );
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

        /*
         * Templates belong to PlmReference.
         *
         * The parser's template definition is converted
         * into the reference resolver's name => text map.
         */
        $this->reference->setTemplates(
            $this->getTemplates(
                $params['template'] ?? []
            )
        );

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
             */
            $lookupFilters =
                $this->extractLookupFilters(
                    $filter
                );

            $structFilter =
                $lookupFilters['filter'];

            /*
             * Lookup fields must be present in the
             * Struct result so their referenced RIDs
             * can be inspected.
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
             * Remove PLM pseudo fields before Struct.
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
     * Convert parser template definitions into the
     * name => template text format expected by PlmReference.
     */
    private function getTemplates(
        array $template
    ): array {

        if (
            empty($template) ||
            !isset($template[0])
        ) {
            return [];
        }

        $templates = [];

        $name =
            trim(
                (string) $template[0]
            );

        if ($name === '') {
            return [];
        }

        $text =
            implode(
                ' ',
                array_slice(
                    $template,
                    2
                )
            );

        $templates[$name] =
            $text;

        return $templates;
    }

    /**
     * Normalize PLM pseudo fields before passing them
     * to Struct.
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
                        $this->reference->resolve(
                            '&' . $match[1]
                        );

                    if ($value === null || $value === '') {
                        $hasEmptyParameter = true;
                        $value = '';
                    }

                    return $this->escapeFilterValue(
                        $value
                    );
                },
                $filter
            );

        /*
         * PLM state references.
         */
        $filter =
            preg_replace_callback(
                '/%([a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+)/',
                function ($match)
                    use (&$hasEmptyParameter) {

                    $value =
                        $this->reference->resolve(
                            '%' . $match[1]
                        );

                    if ($value === null || $value === '') {
                        $hasEmptyParameter = true;
                        $value = '';
                    }

                    return $this->escapeFilterValue(
                        $value
                    );
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
     * Remove logical debris left after special
     * filter conditions have been extracted.
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
     * Build a Struct filter from the table's
     * context filter scope.
     */
    private function buildStateFilter(
        string $name
    ): ?string {

        $values =
            $this->state->getScope(
                $name,
                'filter'
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

        /*
         * Delete no longer references a Struct field.
         * The current row RID is used directly.
         */

        /*
         * Template references are resolved by PlmReference.
         */
        foreach (
            $this->getTemplates(
                $params['template'] ?? []
            )
            as $template
        ) {

            preg_match_all(
                '/\$([a-zA-Z0-9_-]+(?:\._pk)?)/',
                $template,
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

                if (
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

        $value =
            $this->reference->resolve(
                '&' . $name
            );

        return $value ?? '';
    }

    /**
     * Store the values of the current row in:
     *
     *     context.current.*
     *
     * The current row is deliberately selected only once
     * per table render. An existing valid current RID from
     * the PLM state is preserved. This prevents the last
     * rendered row from overwriting the row selected by
     * the user.
     */
    private function storeCurrentRow(
        string $context,
        array $row,
        array $fieldIndexes,
        int $rid
    ): void {

        $current = [
            PlmStruct::PRIMARY_KEY_FIELD =>
                (string) $rid,
        ];

        foreach (
            $fieldIndexes as $field => $index
        ) {

            if (
                !array_key_exists(
                    $index,
                    $row
                )
            ) {
                continue;
            }

            $value =
                $row[$index];

            if ($value === null) {
                continue;
            }

            $displayValue =
                $value->getDisplayValue();

            if ($displayValue === null) {
                continue;
            }

            $current[$field] =
                (string) $displayValue;
        }

        $this->state->setScope(
            $context,
            'current',
            $current
        );
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

        $deleteLabel =
            $this->getDeleteLabel(
                $params
            );

        $details =
            $this->getDetails(
                $params
            );

        $hasActions =
            !empty($filterFields) ||
            !empty($createFields) ||
            $deleteLabel !== null ||
            $details !== null;

        /*
         * Determine which row is currently selected.
         *
         * If the state already contains a current RID and
         * that RID is still part of the current result set,
         * preserve it.
         *
         * Otherwise the first available row becomes current.
         *
         * This is important because the table may contain
         * several rows. The old implementation stored every
         * row as current while rendering, which meant that
         * the last row always overwrote the user's selection.
         */
        $rids =
            $search->getRids();

        $currentRidValue =
            $this->state->getValue(
                $name,
                'current',
                PlmStruct::PRIMARY_KEY_FIELD
            );

        $currentRid =
            $currentRidValue !== ''
                ? (int) $currentRidValue
                : 0;

        if (
            $currentRid <= 0 ||
            !in_array(
                $currentRid,
                $rids,
                true
            )
        ) {

            $currentRid =
                !empty($rids)
                    ? (int) $rids[0]
                    : 0;
        }

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

        if ($deleteLabel !== null) {

            $this->renderer->doc .=
                '<input type="hidden" ' .
                'name="plm_delete[_pk]" ' .
                'value="">';
        }

        if ($details !== null) {

            $this->renderer->doc .=
                '<input type="hidden" ' .
                'name="plm_details[_pk]" ' .
                'value="">' .
                '<input type="hidden" ' .
                'name="plm_redirects[details]" ' .
                'value="' .
                hsc($details['target']) .
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

                $template =
                    $this->reference->resolve(
                        '@' . $templateName
                    );

                $label =
                    $this->getTemplateLabel(
                        $params['template'] ?? [],
                        $templateName
                    );

                $this->renderer->cdata(
                    $label
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

            /*
             * Create editors use the actual Struct
             * column type.
             *
             * Therefore:
             *
             *     Text     -> text input
             *     Dropdown -> select
             *     Lookup   -> lookup select
             *
             * The filter row deliberately remains a
             * free text field because PLM filters use
             * wildcard matching.
             */
            $this->renderCreateRow(
                $columns,
                $createFields,
                $fieldIndexes,
                $search
            );
        }

        if (!empty($filterFields)) {

            /*
             * Filter deliberately remains text based.
             *
             * This allows:
             *
             *     value
             *     *value*
             *
             * and PLM's wildcard matching.
             */
            $this->renderFilterRow(
                $name,
                $columns,
                $filterFields
            );
        }

        foreach ($rows as $rowIndex => $row) {

            $rid =
                (int) (
                    $rids[$rowIndex] ?? 0
                );

            /*
             * Make the current row and its RID
             * available to PlmReference.
             *
             * IMPORTANT:
             *
             * Do this only for the selected row.
             * Otherwise each rendered row would overwrite
             * context.current and the last row would always
             * become the current row.
             */
            if (
                $rid === $currentRid
            ) {

                $this->reference->setRow(
                    $row,
                    $fieldIndexes,
                    $rid
                );

                $this->storeCurrentRow(
                    $name,
                    $row,
                    $fieldIndexes,
                    $rid
                );
            }

            $this->renderer->tablerow_open();

            foreach ($columns as $column) {

                $this->renderer->tablecell_open();

                if (
                    str_starts_with(
                        $column,
                        '@'
                    )
                ) {

                    /*
                     * Template references may be used
                     * independently of the current row.
                     *
                     * For row-specific templates the
                     * reference resolver receives the row
                     * immediately below.
                     */
                    $this->reference->setRow(
                        $row,
                        $fieldIndexes,
                        $rid
                    );

                    $expanded =
                        $this->reference->expand(
                            $column
                        );

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

                if ($deleteLabel !== null) {

                    $deleteRid =
                        hsc((string) $rid);

                    $this->renderer->doc .=
                        '<button type="submit" ' .
                        'name="plm_action" ' .
                        'value="delete" ' .
                        'class="plm_table_delete_button" ' .
                        'onclick="this.form.elements[\'plm_delete[_pk]\'].value=\'' .
                        $deleteRid .
                        '\';">' .
                        hsc($deleteLabel) .
                        '</button>';
                }

                if ($details !== null) {

                    $detailsRid =
                        hsc((string) $rid);

                    $this->renderer->doc .=
                        '<button type="submit" ' .
                        'name="plm_action" ' .
                        'value="details" ' .
                        'class="plm_table_details_button" ' .
                        'onclick="this.form.elements[\'plm_details[_pk]\'].value=\'' .
                        $detailsRid .
                        '\';">' .
                        hsc($details['label']) .
                        '</button>';
                }

                $this->renderer->tablecell_close();
            }

            $this->renderer->tablerow_close();
        }

        /*
         * The row is no longer valid after rendering.
         */
        $this->reference->clearRow();

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

    /**
     * Get the label of a named template.
     */
    private function getTemplateLabel(
        array $template,
        string $name
    ): string {

        if (
            empty($template) ||
            ($template[0] ?? null) !== $name
        ) {
            return $name;
        }

        return
            $template[1] ??
            $name;
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

    private function getDetails(
        array $params
    ): ?array {

        if (
            !isset($params['details']) ||
            !is_array($params['details'])
        ) {
            return null;
        }

        $details =
            array_values(
                $params['details']
            );

        if (count($details) !== 2) {
            return null;
        }

        $label =
            trim(
                (string) $details[0]
            );

        $target =
            trim(
                (string) $details[1]
            );

        if ($label === '' || $target === '') {
            return null;
        }

        return [
            'label' => $label,
            'target' => $target,
        ];
    }

    private function getDeleteLabel(
        array $params
    ): ?string {

        if (
            !isset($params['delete']) ||
            !is_string($params['delete'])
        ) {
            return null;
        }

        $label =
            trim(
                $params['delete']
            );

        if ($label === '') {
            return null;
        }

        return $label;
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

    /**
     * Render the create row.
     *
     * Create fields use the actual Struct type.
     *
     * This is important for:
     *
     *     Dropdown
     *     Lookup
     *     Multi-value fields
     *
     * The corresponding Struct editor is used instead
     * of always rendering a plain text input.
     */
    private function renderCreateRow(
        array $columns,
        array $createFields,
        array $fieldIndexes,
        $search
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
                $this->renderCreateEditor(
                    $column,
                    $fieldIndexes,
                    $search
                );

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

    /**
     * Render the editor for one Create field.
     *
     * The Struct type itself is responsible for deciding
     * whether the editor is:
     *
     *     input
     *     select
     *     lookup
     *     multi-select
     *     etc.
     */
    private function renderCreateEditor(
        string $field,
        array $fieldIndexes,
        $search
    ): string {

        if (
            !isset(
                $fieldIndexes[$field]
            )
        ) {

            return $this->renderCreateTextInput(
                $field
            );
        }

        $columns =
            $search->getColumns();

        $index =
            $fieldIndexes[$field];

        $column =
            $columns[$index] ?? null;

        if (
            !is_object($column) ||
            !method_exists(
                $column,
                'getType'
            )
        ) {

            return $this->renderCreateTextInput(
                $field
            );
        }

        $type =
            $column->getType();

        /*
         * Multi-value fields use Struct's
         * multi-value editor.
         */
        if (
            method_exists(
                $column,
                'isMulti'
            ) &&
            $column->isMulti()
        ) {

            return
                $type->multiValueEditor(
                    'plm_create[' . $field . ']',
                    [],
                    'plm_create_' .
                    $this->htmlId($field)
                );
        }

        /*
         * Normal fields use Struct's value editor.
         *
         * Dropdown -> <select>
         * Lookup   -> Lookup <select>
         * Text     -> <input>
         * etc.
         */
        return
            $type->valueEditor(
                'plm_create[' . $field . ']',
                '',
                'plm_create_' .
                $this->htmlId($field)
            );
    }

    /**
     * Fallback text input for Create fields where
     * Struct does not provide a usable column/type.
     */
    private function renderCreateTextInput(
        string $field
    ): string {

        return
            '<input type="text" ' .
            'name="plm_create[' .
            hsc($field) .
            ']" ' .
            'value="" ' .
            'placeholder="' .
            hsc(
                'Create ' . $field
            ) .
            '" ' .
            'onkeydown="' .
            'if(event.key===\'Enter\'){' .
            'event.preventDefault();' .
            'event.stopPropagation();' .
            'return false;' .
            '}' .
            '">';
    }

    /**
     * Generate a safe HTML id from a Struct field name.
     */
    private function htmlId(
        string $field
    ): string {

        return preg_replace(
            '/[^a-zA-Z0-9_-]+/',
            '_',
            $field
        );
    }

    /**
     * Render the filter row.
     *
     * Deliberately uses text inputs for all fields.
     *
     * This is required for PLM's wildcard search:
     *
     *     foo
     *     *foo*
     *
     * The filter value is later converted into a
     * Struct wildcard filter.
     */
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
                $this->state->getValue(
                    $name,
                    'filter',
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
