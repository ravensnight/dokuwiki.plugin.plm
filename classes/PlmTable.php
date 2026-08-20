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

            $result =
                $this->struct->search(
                    $schema,
                    $fields,
                    $filter
                );

            $search =
                $result['search'];

            $rows =
                $result['rows'];

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
     * Expand URI parameters in an explicit
     * PLM filter.
     */
    private function expandFilter(
        string $filter
    ): ?string {

        if ($filter === '') {
            return null;
        }

        $hasEmptyParameter = false;

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

        if ($hasEmptyParameter) {
            return null;
        }

        return $filter;
    }

    /**
     * Build a Struct filter from the current
     * PLM state.
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

        foreach ($values as $field => $value) {

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
     * Escape characters which have a special
     * meaning in Struct filter values.
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
     * Determine all Struct fields required by
     * the table.
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

            /*
             * "_pk" is a PLM technical field and
             * does not exist in the Struct schema.
             */
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
         * Delete field must always be available
         * in every data row.
         *
         * "_pk" is technical and therefore does
         * not need to be added to Struct columns.
         */
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

        /*
         * Fields referenced by templates.
         */
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

                /*
                 * "_pk" is resolved from the
                 * SearchConfig RID.
                 */
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

        return array_values(
            array_unique(
                $fields
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
     *
     * Syntax:
     *
     *     template: link_edit "Mein Link" [[ page | Open ]]
     *
     * Returns:
     *
     *     [
     *         'name' => 'link_edit',
     *         'label' => 'Mein Link',
     *         'text' => '[[ page | Open ]]'
     *     ]
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
     *
     * "_pk" is resolved from the Struct RID.
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

        /*
         * Expand Struct fields and the
         * technical "_pk" field.
         */
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

                    /*
                     * Technical PLM primary key.
                     */
                    if (
                        $field ===
                        PlmStruct::PRIMARY_KEY_FIELD
                    ) {

                        if ($rid === null) {
                            return $match[0];
                        }

                        return (string) $rid;
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

        /*
         * Expand URI parameters.
         */
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

        /*
         * Common form.
         */
        if ($hasActions) {

            $this->renderer->doc .=
                '<form method="post" '
                . 'class="plm_table_form" '
                . 'onkeydown="'
                . 'if(event.key===\'Enter\' && '
                . 'event.target.tagName!==\'BUTTON\'){'
                . 'event.preventDefault();'
                . 'event.stopPropagation();'
                . 'return false;'
                . '}'
                . '">' .

                '<input type="hidden" '
                . 'name="plm_form_submit" '
                . 'value="1">' .

                '<input type="hidden" '
                . 'name="plm_table" '
                . 'value="'
                . hsc($name)
                . '">' .

                '<input type="hidden" '
                . 'name="plm_schema" '
                . 'value="'
                . hsc($schema)
                . '">' .

                '<input type="hidden" '
                . 'name="sectok" '
                . 'value="'
                . hsc(
                    getSecurityToken()
                )
                . '">';
        }

        $this->renderer->table_open();

        /*
         * -----------------------------------------------------
         * HEADER
         * -----------------------------------------------------
         */

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

                /*
                 * For template columns the second
                 * template token is the column label.
                 */
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

        /*
         * ACTION HEADER IS LAST.
         */
        if ($hasActions) {

            $this->renderer->tableheader_open();

            $this->renderer->cdata(
                'Action'
            );

            $this->renderer->tableheader_close();
        }

        $this->renderer->tablerow_close();

        /*
         * -----------------------------------------------------
         * CREATE ROW
         * -----------------------------------------------------
         */

        if (!empty($createFields)) {

            $this->renderCreateRow(
                $columns,
                $createFields
            );
        }

        /*
         * -----------------------------------------------------
         * FILTER ROW
         * -----------------------------------------------------
         */

        if (!empty($filterFields)) {

            $this->renderFilterRow(
                $name,
                $columns,
                $filterFields
            );
        }

        /*
         * -----------------------------------------------------
         * DATA ROWS
         * -----------------------------------------------------
         */

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

                    /*
                     * Technical Struct RID.
                     */
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

            /*
             * ACTION CELL IS LAST.
             */
            if ($hasActions) {

                $this->renderer->tablecell_open();

                /*
                 * Delete button.
                 */
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
                            '<button type="submit" '
                            . 'name="plm_action" '
                            . 'value="delete" '
                            . 'class="plm_table_delete_button">'
                            . hsc('Delete')
                            . '</button>'

                            . '<input type="hidden" '
                            . 'name="plm_delete['
                            . hsc($deleteField)
                            . ']" '
                            . 'value="'
                            . hsc($deleteValue)
                            . '">';
                    }
                }

                $this->renderer->tablecell_close();
            }

            $this->renderer->tablerow_close();
        }

        /*
         * -----------------------------------------------------
         * EMPTY RESULT
         * -----------------------------------------------------
         */

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
     * Get explicitly filterable fields.
     */
    private function getFilterFields(
        array $params
    ): array {

        return $this->getConfiguredFields(
            $params['filter'] ?? []
        );
    }

    /**
     * Get fields required for CREATE.
     */
    private function getCreateFields(
        array $params
    ): array {

        return $this->getConfiguredFields(
            $params['create'] ?? []
        );
    }

    /**
     * Get the configured DELETE field.
     */
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

    /**
     * Normalize a configured field list.
     */
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
     * Render the CREATE row.
     */
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
                '<input type="text" '
                . 'name="plm_create['
                . hsc($column)
                . ']" '
                . 'value="" '
                . 'placeholder="'
                . hsc(
                    'Create ' . $column
                )
                . '" '
                . 'onkeydown="'
                . 'if(event.key===\'Enter\'){'
                . 'event.preventDefault();'
                . 'event.stopPropagation();'
                . 'return false;'
                . '}'
                . '">';

            $this->renderer->tablecell_close();
        }

        /*
         * Empty action cell at the end.
         */
        $this->renderer->tablecell_open();

        $this->renderer->doc .=
            '<button type="submit" '
            . 'name="plm_action" '
            . 'value="create" '
            . 'class="plm_table_create_button">'
            . hsc('Create')
            . '</button>';

        $this->renderer->tablecell_close();

        $this->renderer->tablerow_close();
    }

    /**
     * Render the FILTER row.
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
                $this->state->getFilterValue(
                    $name,
                    $column
                );

            $this->renderer->doc .=
                '<input type="text" '
                . 'name="plm_filter['
                . hsc($column)
                . ']" '
                . 'value="'
                . hsc($value)
                . '" '
                . 'placeholder="'
                . hsc(
                    'Filter ' . $column
                )
                . '" '
                . 'onkeydown="'
                . 'if(event.key===\'Enter\'){'
                . 'event.preventDefault();'
                . 'event.stopPropagation();'
                . 'return false;'
                . '}'
                . '">';

            $this->renderer->tablecell_close();
        }

        /*
         * Filter button at the end.
         */
        $this->renderer->tablecell_open();

        $this->renderer->doc .=
            '<button type="submit" '
            . 'name="plm_action" '
            . 'value="filter" '
            . 'class="plm_table_filter_button">'
            . hsc('Filter')
            . '</button>';

        $this->renderer->tablecell_close();

        $this->renderer->tablerow_close();
    }

    /**
     * Display a technical PLM error.
     */
    private function error(
        string $message
    ): void {

        $this->renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }
}