<?php

class PlmSelect
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmState */
    private $state;

    /*
     * Central definition of a PLM field name.
     *
     * Important:
     *
     *     $name__
     *
     * must be parsed as:
     *
     *     $name
     *     __
     *
     * and NOT as the field "$name__".
     *
     * A field may contain separators such as:
     *
     *     company_name
     *     company-name
     *     company.name
     *
     * but separators must not appear at the beginning
     * or end of the field name.
     */
    private const FIELD_PATTERN =
        '[a-zA-Z0-9]+(?:[_.-][a-zA-Z0-9]+)*';

    /**
     * Pattern for PLM state references.
     *
     * Example:
     *
     *     $tablecompanies.name
     */
    private const STATE_PATTERN =
        '[a-zA-Z0-9_-]+\.'
        . '[a-zA-Z0-9]+(?:[_.-][a-zA-Z0-9]+)*';

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct,
        PlmState $state
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
        $this->state = $state;
    }

    public function render(
        string $name,
        string $schema,
        string $filter,
        string $content,
        string $errortext = 'not found!'
    ): void {

        if ($schema === '') {
            $this->error(
                'PLM select: parameter "schema" is required'
            );
            return;
        }

        if ($filter === '') {
            $this->error(
                'PLM select: parameter "filter" is required'
            );
            return;
        }

        /*
         * Expand references to PLM table filters.
         *
         * Example:
         *
         *     name=*$tablecompanies.name*
         *
         * becomes:
         *
         *     name=*Anycubic*
         */
        $filter =
            $this->expandFilter(
                $filter
            );

        /*
         * If a referenced state value does not exist,
         * no record can be selected.
         *
         * The select still renders its content.
         * Only field placeholders are replaced
         * by the error text.
         */
        if ($filter === null) {

            $text =
                $this->expandContent(
                    $content,
                    null,
                    [],
                    $errortext
                );

            $this->renderContent(
                $text
            );

            return;
        }

        try {

            $fields =
                $this->getStructFields(
                    $filter,
                    $content
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
                'PLM select: ' .
                $e->getMessage()
            );

            return;
        }

        /*
         * PLM select deliberately uses only the
         * first matching record.
         */
        if (empty($rows)) {

            $text =
                $this->expandContent(
                    $content,
                    null,
                    [],
                    $errortext
                );

            $this->renderContent(
                $text
            );

            return;
        }

        /*
         * Use only the first matching record.
         */
        $row =
            $rows[0];

        /*
         * Build field indexes.
         */
        $fieldIndexes = [];

        foreach (
            $search->getColumns()
            as $index => $column
        ) {

            $field =
                $column->getLabel();

            $fieldIndexes[$field] =
                $index;
        }

        /*
         * Render the content once for the first
         * matching record.
         */
        $text =
            $this->expandContent(
                $content,
                $row,
                $fieldIndexes,
                $errortext
            );

        $this->renderContent(
            $text
        );
    }

    /**
     * Expand references to values stored in PlmState.
     *
     * Example:
     *
     *     name=*$tablecompanies.name*
     *
     * becomes:
     *
     *     name=*Anycubic*
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
                '/\$('
                . self::STATE_PATTERN
                . ')/',
                function ($match)
                    use (&$hasEmptyParameter) {

                    $reference =
                        $match[1];

                    $parts =
                        explode(
                            '.',
                            $reference,
                            2
                        );

                    $table =
                        $parts[0];

                    $field =
                        $parts[1];

                    $value =
                        $this->state->getFilterValue(
                            $table,
                            $field
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
     * Determine Struct fields referenced by the filter
     * or by the select content.
     */
    private function getStructFields(
        string $filter,
        string $content
    ): array {

        $fields = [];

        /*
         * Fields used on the left side of the filter.
         */
        preg_match_all(
            '/(?:^|\(|\s|AND\s+|OR\s+)'
            . '('
            . self::FIELD_PATTERN
            . ')'
            . '\s*(?:=|!=|~|!~|=\*|>\=|<\=|>|<)/i',
            $filter,
            $matches
        );

        foreach (
            $matches[1] ?? []
            as $field
        ) {

            $fields[] =
                $field;
        }

        /*
         * Fields referenced in the select content.
         *
         * Example:
         *
         *     Change: $name
         *
         * requires:
         *
         *     name
         */
        preg_match_all(
            '/\$('
            . self::FIELD_PATTERN
            . ')/',
            $content,
            $matches
        );

        foreach (
            $matches[1] ?? []
            as $field
        ) {

            /*
             * A select content placeholder is a Struct
             * field, not a PLM state reference.
             */
            if (
                strpos(
                    $field,
                    '.'
                ) === false
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
     * Expand $field references in the select body.
     *
     * If a matching record exists, the placeholder
     * is replaced by the Struct field value.
     *
     * If no matching record exists, only the
     * placeholder itself is replaced by $errortext.
     *
     * Example:
     *
     *     __Change $name__
     *
     * becomes:
     *
     *     __Change Anycubic__
     *
     * or, if no record exists:
     *
     *     __Change Element not found__
     */
    private function expandContent(
        string $content,
        ?array $row,
        array $fieldIndexes,
        string $errortext
    ): string {

        return
            preg_replace_callback(
                '/\$('
                . self::FIELD_PATTERN
                . ')/',
                function ($match)
                    use (
                        $row,
                        $fieldIndexes,
                        $errortext
                    ) {

                    $field =
                        $match[1];

                    /*
                     * No matching record.
                     *
                     * Replace only the placeholder.
                     */
                    if ($row === null) {

                        return $errortext;
                    }

                    /*
                     * Field was not loaded.
                     *
                     * Treat it like a missing value.
                     */
                    if (
                        !isset(
                            $fieldIndexes[$field]
                        )
                    ) {

                        return $errortext;
                    }

                    $value =
                        $row[
                            $fieldIndexes[$field]
                        ]->getDisplayValue();

                    /*
                     * Empty Struct value.
                     *
                     * The record exists, but the field
                     * itself has no value. In this case
                     * use the error text as well.
                     */
                    if (
                        $value === null ||
                        $value === ''
                    ) {

                        return $errortext;
                    }

                    return $value;
                },
                $content
            );
    }

    /**
     * Render expanded DokuWiki content.
     */
    private function renderContent(
        string $text
    ): void {

        /*
         * Do not render completely empty content.
         */
        if (
            trim($text) === ''
        ) {
            return;
        }

        $instructions =
            p_get_instructions(
                $text
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

    private function error(
        string $message
    ): void {

        $this->renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }
}