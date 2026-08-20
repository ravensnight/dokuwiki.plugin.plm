<?php

class PlmSelect
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmState */
    private $state;

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
             * State parameters.
             *
             * Example:
             *
             *     $tablecompanies.name
             *
             * becomes the value stored in:
             *
             *     filter.tablecompanies.name
             */
            $filter =
                $this->replaceStateParameters(
                    $filter
                );

            /*
             * URI parameters.
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

            if ($record === null) {
                return;
            }

            /*
             * Get access for exact record.
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
             * Replace variables in content.
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

    /**
     * Replace:
     *
     *     $tablecompanies.name
     *
     * with the value from PlmState.
     */
    private function replaceStateParameters(
        string $text
    ): string {

        return preg_replace_callback(
            '/\$([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_.-]+)/',
            function ($match) {

                return $this->state->get(
                    'filter.' .
                    $match[1] .
                    '.' .
                    $match[2]
                );
            },
            $text
        );
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

                if ($value === '') {
                    return '';
                }

                return $whitespace . $value;
            },
            $text
        );
    }

    private function replaceVariables(
        string $content,
        array $data
    ): string {

        /*
         * State variables.
         */
        $content =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_.-]+)/',
                function ($match) {

                    return $this->state->get(
                        'filter.' .
                        $match[1] .
                        '.' .
                        $match[2]
                    );
                },
                $content
            );

        /*
         * Struct fields.
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
         * URI parameters.
         */
        $content =
            $this->replaceUriParameters(
                $content
            );

        return $content;
    }

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

    private function renderMarkup(
        string $content
    ): void {

        if (trim($content) === '') {
            return;
        }

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

    private function error(
        string $message
    ): void {

        $this->renderer->doc .=
            '<div class="error">' .
            hsc($message) .
            '</div>';
    }
}