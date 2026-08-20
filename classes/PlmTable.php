<?php

class PlmTable
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmParser */
    private $parser;

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct,
        PlmParser $parser
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
        $this->parser = $parser;
    }

    public function render(
        string $schema,
        string $filter,
        string $content
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
            $columns,
            $search,
            $rows,
            $params
        );
    }

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

    private function getStructFields(
        array $params
    ): array {
        $fields = [];

        foreach (
            $params['cols'] ?? []
            as $col
        ) {
            $fields[] = $col;
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
                $matches[1]
                as $field
            ) {
                $fields[] = $field;
            }
        }

        return array_values(
            array_unique($fields)
        );
    }

    private function getUriParam(
        string $name
    ): string {
        global $INPUT;

        return $INPUT->str($name) ?? '';
    }

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

        /*
         * Token 0 = Template name
         * Token 1 = Label
         * Token 2+ = Template content
         */
        $text =
            implode(
                ' ',
                array_slice(
                    $template,
                    2
                )
            );

        /*
         * Struct fields:
         *
         *     $ipn
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
         * URI parameters:
         *
         *     &ipn
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

    private function renderTable(
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

        $this->renderer->table_open();

        /*
         * Header
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

                $name =
                    substr(
                        $column,
                        1
                    );

                if (
                    isset($params['template']) &&
                    ($params['template'][0] ?? null)
                        === $name
                ) {

                    $this->renderer->cdata(
                        $params['template'][1]
                            ?? $name
                    );

                } else {

                    $this->renderer->cdata(
                        $name
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
         * Rows
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

                    $name =
                        substr(
                            $column,
                            1
                        );

                    $expanded =
                        $this->expandTemplate(
                            $params['template'] ?? [],
                            $name,
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

    private function error(
        string $message
    ): void {
        $this->renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }
}