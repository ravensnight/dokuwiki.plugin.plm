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

            /*
             * Technical errors, such as a missing
             * Struct schema, remain real PLM errors.
             */
            $this->error(
                'PLM table: ' .
                $e->getMessage()
            );

            return;
        }

        /*
         * No matching records are not considered
         * a technical error.
         *
         * Instead of rendering an empty table,
         * display the configured error text.
         */
        if (empty($rows)) {

            $this->notFound(
                $errortext
            );

            return;
        }

        $this->renderTable(
            $name,
            $columns,
            $search,
            $rows,
            $params
        );
    }

    /**
     * Expand URI parameters in an explicit
     * PLM filter.
     *
     * Example:
     *
     *     name=&company
     *
     * becomes:
     *
     *     name=Anycubic
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
     *
     * Example:
     *
     *     name = Any
     *
     * becomes:
     *
     *     name~*Any*
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
     * Combine the explicit PLM filter with
     * the filters stored in PlmState.
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
     *
     * Fields can originate from:
     *
     * - cols
     * - filter
     * - template
     */
    private function getStructFields(
        array $params
    ): array {

        $fields = [];

        /*
         * Fields displayed by the table.
         */
        foreach (
            $params['cols'] ?? []
            as $col
        ) {

            /*
             * Template columns do not directly
             * represent Struct fields.
             */
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
         * Fields explicitly declared as
         * filterable.
         *
         * These must also be loaded from Struct,
         * even when they are not visible columns.
         */
        foreach (
            $params['filter'] ?? []
            as $field
        ) {

            $fields[] =
                $field;
        }

        /*
         * Fields referenced by templates.
         *
         * Example:
         *
         *     template: details "Details: $name"
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

        /*
         * Expand Struct fields.
         *
         * Example:
         *
         *     $name
         *
         * becomes:
         *
         *     Anycubic
         */
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
        array $columns,
        $search,
        array $rows,
        array $params
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
         * Filter fields explicitly declared
         * by the user.
         */
        $filterFields =
            $this->getFilterFields(
                $params
            );

        $this->renderer->table_open();

        /*
         * Header.
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

        $this->renderer->tablerow_close();

        /*
         * Optional filter row.
         *
         * A filter row is rendered only when
         * at least one field was declared with
         *
         *     filter: name, description
         */
        if (!empty($filterFields)) {

            $this->renderFilterRow(
                $name,
                $columns,
                $filterFields
            );
        }

        /*
         * Rows.
         */
        foreach ($rows as $row) {

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

                } else {

                    if (
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
                }

                $this->renderer->tablecell_close();
            }

            $this->renderer->tablerow_close();
        }

        $this->renderer->table_close();
    }

    /**
     * Get explicitly filterable fields.
     *
     * Example:
     *
     *     filter: name, description
     *
     * becomes:
     *
     *     [
     *         'name',
     *         'description'
     *     ]
     */
    private function getFilterFields(
        array $params
    ): array {

        $fields = [];

        foreach (
            $params['filter'] ?? []
            as $field
        ) {

            $field =
                trim(
                    $field
                );

            if ($field === '') {
                continue;
            }

            $fields[] =
                $field;
        }

        return array_values(
            array_unique(
                $fields
            )
        );
    }

    /**
     * Render the filter row.
     *
     * Only fields declared with
     *
     *     filter: ...
     *
     * receive an input field.
     *
     * The filter is submitted using POST.
     * The POST request is converted by
     * syntax_plugin_plm into a clean
     * ?plm=<encoded-state> URL.
     */
    private function renderFilterRow(
        string $name,
        array $columns,
        array $filterFields
    ): void {

        $this->renderer->tablerow_open();

        foreach ($columns as $column) {

            $this->renderer->tablecell_open();

            /*
             * Template columns are not directly
             * searchable.
             */
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

            $html =
                '<form method="post" '
                . 'class="plm_table_filter_form">'
                . '<input type="text" '
                . 'name="plm_filter_value" '
                . 'value="'
                . hsc($value)
                . '" '
                . 'placeholder="'
                . hsc(
                    'Filter ' . $column
                )
                . '">'
                . '<input type="hidden" '
                . 'name="plm_filter_table" '
                . 'value="'
                . hsc($name)
                . '">'
                . '<input type="hidden" '
                . 'name="plm_filter_field" '
                . 'value="'
                . hsc($column)
                . '">'
                . '</form>';

            $this->renderer->doc .=
                $html;

            $this->renderer->tablecell_close();
        }

        $this->renderer->tablerow_close();
    }

    /**
     * Display a technical PLM error.
     */
    private function error(
        string $message
    ): void {

        $this->renderer->doc .=
            '<div class="error">'
            .
            hsc($message)
            .
            '</div>';
    }

    /**
     * Display the configured "not found" text.
     *
     * This is intentionally not rendered as
     * a DokuWiki error box.
     */
    private function notFound(
        string $message
    ): void {

        $this->renderer->doc .=
            '<div class="plm_empty">'
            .
            hsc($message)
            .
            '</div>';
    }
}