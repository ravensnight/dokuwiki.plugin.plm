<?php

class PlmSelect
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
    }

    /**
     * Render the first matching Struct record.
     *
     * Example:
     *
     * /plm:select > plm_part[ipn~*&ipn*]
     * __Current Filter: &ipn__
     * User: &user
     * /plm
     */
    public function render(
        string $schema,
        string $filter,
        string $content
    ): void {
        try {

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
             * Expand URI parameters in the filter.
             *
             * Example:
             *
             *     ipn~*&ipn*
             *
             * becomes:
             *
             *     ipn~*PRD*
             */
            $filter =
                $this->replaceUriParameters(
                    $filter
                );

            /*
             * Find first matching record.
             */
            $record =
                $this->struct->findOne(
                    $schema,
                    $filter
                );

            /*
             * No matching record:
             *
             * render nothing.
             */
            if ($record === null) {
                return;
            }

            /*
             * Get access for the exact record.
             */
            $access =
                $this->struct->getAccessForRecord(
                    $schema,
                    $record['pid'],
                    $record['rid']
                );

            /*
             * Get Struct values.
             */
            $data =
                $this->struct->getData(
                    $access
                );

            /*
             * Replace variables in the content.
             *
             * $field = Struct field
             * &param  = URI parameter
             */
            $content =
                $this->replaceVariables(
                    $content,
                    $data
                );

            /*
             * Render resulting DokuWiki markup.
             */
            $this->renderMarkup(
                $content
            );

        } catch (Throwable $e) {

            $this->error(
                'PLM select: ' .
                $e->getMessage()
            );
        }
    }

    private function replaceUriParameters(
        string $text
    ): string {

        return preg_replace_callback(
            '/([ \t]*)&([a-zA-Z0-9-]+)/',
            function ($match) {

                $whitespace = $match[1];

                $value =
                    $this->getUriParam(
                        $match[2]
                    );

                /*
                * Empty parameter:
                *
                *     " &ipn" -> ""
                */
                if ($value === '') {
                    return '';
                }

                /*
                * Parameter exists:
                *
                *     " &ipn" -> " PRD"
                */
                return $whitespace . $value;
            },
            $text
        );
    }

    /**
     * Replace variables in the Select content.
     *
     * Struct fields:
     *
     *     $ipn
     *     $description
     *
     * URI parameters:
     *
     *     &ipn
     *     &user
     */
    private function replaceVariables(
        string $content,
        array $data
    ): string {

        /*
         * -----------------------------------------------------
         * Struct fields
         * -----------------------------------------------------
         *
         * Example:
         *
         *     $ipn
         *
         * becomes:
         *
         *     PRD-123
         */
        $content =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_.-]+)/',
                function ($match) use ($data) {

                    return $this->getColumnValue(
                        $data,
                        $match[1]
                    );
                },
                $content
            );

        /*
         * -----------------------------------------------------
         * URI parameters
         * -----------------------------------------------------
         *
         * Example:
         *
         *     &ipn
         *
         * becomes:
         *
         *     PRD-123
         *
         * Because "_" is not part of the URI parameter
         * syntax, this also works:
         *
         *     __Current Filter: &ipn__
         *
         * resulting in:
         *
         *     __Current Filter: PRD-123__
         */
        $content =
            $this->replaceUriParameters(
                $content
            );

        return $content;
    }

    /**
     * Get Struct column value.
     */
    private function getColumnValue(
        array $data,
        string $name
    ): string {

        if (!array_key_exists(
            $name,
            $data
        )) {
            return '';
        }

        $value =
            $data[$name];

        /*
         * Struct value object.
         */
        if (
            is_object($value) &&
            method_exists(
                $value,
                'getDisplayValue'
            )
        ) {

            $value =
                $value->getDisplayValue();
        }

        /*
         * Multi-value field.
         */
        if (is_array($value)) {

            return implode(
                ', ',
                array_map(
                    'strval',
                    $value
                )
            );
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Render generated DokuWiki markup.
     */
    private function renderMarkup(
        string $content
    ): void {

        if (trim($content) === '') {
            return;
        }

        /*
         * Parse generated content as DokuWiki markup.
         */
        $instructions =
            p_get_instructions(
                $content
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

    /**
     * Get URI parameter.
     */
    private function getUriParam(
        string $name
    ): string {

        global $INPUT;

        $value =
            $INPUT->str($name);

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Display error.
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