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

            /*
             * Expand explicit filter from
             * the PLM header.
             */
            $filter =
                $this->expandFilter(
                    $filter
                );

            /*
             * Add filters from PlmState.
             */
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

        /*
         * Visible columns.
         */
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

            $fields[] =
                $col;
        }

        /*
         * Filter fields.
         */
        foreach (
            $this->getConfiguredFields(
                $params['filter'] ?? []
            )
            as $field
        ) {

            $fields[] =
                $field;
        }

        /*
         * Create fields.
         */
        foreach (
            $this->getConfiguredFields(
                $params['create'] ?? []
            )
            as $field
        ) {

            $fields[] =
                $field;
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
     * Expand a named PLM template.
     */
    private function expandTemplate(
        array $template,
        string $name,
        array $row,
        array $fieldIndexes
    ): ?string {

        if (
            empty($template) ||
            ($template[0] ?? null) !== $name
        ) {
            return null;
        }

        $text =
            implode(
                ' ',
                array_slice(
                    $template,
                    2
                )
            );

        $text =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_.-]+)/',
                function ($match)
                    use ($row, $fieldIndexes) {

                    $field =
                        $match[1];

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

        /*
         * Explicit action fields.
         */
        $filterFields =
            $this->getFilterFields(
                $params
            );

        $createFields =
            $this->getCreateFields(
                $params
            );

        /*
         * Action column exists if at least
         * one action is configured.
         */
        $hasActions =
            !empty($filterFields) ||
            !empty($createFields);

        /*
         * Common form.
         *
         * ENTER is deliberately blocked at form level.
         * Buttons remain normal submit buttons.
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
                . '">'

                . '<input type="hidden" '
                . 'name="plm_form_submit" '
                . 'value="1">'

                . '<input type="hidden" '
                . 'name="plm_table" '
                . 'value="'
                . hsc($name)
                . '">'

                . '<input type="hidden" '
                . 'name="plm_schema" '
                . 'value="'
                . hsc($schema)
                . '">'

                . '<input type="hidden" '
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

        /*
         * Normal columns first.
         */
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

                if (
                    isset($params['template']) &&
                    ($params['template'][0] ?? null)
                        === $templateName
                ) {

                    $this->renderer->cdata(
                        $params['template'][1]
                            ?? $templateName
                    );

                } else {

                    $this->renderer->cdata(
                        $templateName
                    );
                }

            } else {

                $this->renderer->cdata(
                    $fieldLabels[$column]
                        ?? $column
                );
            }

            $this->renderer->tableheader_close();
        }

        /*
         * Action column LAST.
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

        foreach ($rows as $row) {

            $this->renderer->tablerow_open();

            /*
             * Normal data columns first.
             */
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
                            $fieldIndexes
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
             * Empty Action cell LAST.
             */
            if ($hasActions) {

                $this->renderer->tablecell_open();
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

            /*
             * First cell belongs to the first
             * normal column.
             */
            $this->renderer->tablecell_open();

            $this->renderer->doc .=
                '<div class="plm_empty">' .
                hsc($errortext) .
                '</div>';

            $this->renderer->tablecell_close();

            /*
             * Remaining normal columns.
             */
            for (
                $i = 1;
                $i < count($columns);
                $i++
            ) {

                $this->renderer->tablecell_open();
                $this->renderer->tablecell_close();
            }

            /*
             * Action cell LAST.
             */
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
     * Normalize a configured field list.
     *
     * Supports both:
     *
     *     create: ipn description
     *
     * and:
     *
     *     create: ipn, description
     */
    private function getConfiguredFields(
        array $fields
    ): array {

        $result = [];

        foreach ($fields as $field) {

            /*
             * A parser token may still contain commas.
             * Split them here.
             */
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

                if (!preg_match(
                    '/^[a-zA-Z0-9_.-]+$/',
                    $part
                )) {
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
     *
     * The Action cell is deliberately rendered
     * as the LAST column.
     */
    private function renderCreateRow(
        array $columns,
        array $createFields
    ): void {

        $this->renderer->tablerow_open();

        /*
         * Create inputs first.
         */
        foreach ($columns as $column) {

            $this->renderer->tablecell_open();

            /*
             * Template columns do not receive
             * create inputs.
             */
            if (
                str_starts_with(
                    $column,
                    '@'
                )
            ) {

                $this->renderer->tablecell_close();
                continue;
            }

            /*
             * Only explicitly configured create
             * fields receive an input.
             */
            if (!in_array(
                $column,
                $createFields,
                true
            )) {

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
                . '">';

            $this->renderer->tablecell_close();
        }

        /*
         * Create button LAST.
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
     *
     * The Action cell is deliberately rendered
     * as the LAST column.
     */
    private function renderFilterRow(
        string $name,
        array $columns,
        array $filterFields
    ): void {

        $this->renderer->tablerow_open();

        /*
         * Filter fields first.
         */
        foreach ($columns as $column) {

            $this->renderer->tablecell_open();

            if (
                str_starts_with(
                    $column,
                    '@'
                ) ||
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
                . '">';

            $this->renderer->tablecell_close();
        }

        /*
         * Filter button LAST.
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