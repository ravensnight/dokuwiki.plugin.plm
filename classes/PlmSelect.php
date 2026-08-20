<?php

class PlmSelect
{
    private $renderer;

    private $struct;

    private $state;

    private const FIELD_PATTERN =
        '[a-zA-Z0-9]+(?:[_.-][a-zA-Z0-9]+)*';

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

        $filter =
            $this->expandFilter(
                $filter
            );

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

            /*
             * findOne() is deliberately used here.
             *
             * This is important because findOne()
             * centrally handles the special _pk field.
             */
            $record =
                $this->struct->findOne(
                    $schema,
                    $filter
                );

            if ($record === null) {

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

            $row =
                $record['row'];

            /*
             * We need the column indexes from the same
             * Struct search used to obtain the row.
             *
             * Search all requested fields again.
             */
            $fields =
                $this->getStructFields(
                    $filter,
                    $content
                );

            if (empty($fields)) {

                $fields =
                    array_keys(
                        $row
                    );
            }

            $result =
                $this->struct->search(
                    $schema,
                    $fields
                );

            $search =
                $result['search'];

            $rows =
                $result['rows'];

            $fieldIndexes = [];

            foreach (
                $search->getColumns()
                as $index => $column
            ) {

                $fieldIndexes[
                    $column->getLabel()
                ] =
                    $index;
            }

            /*
             * Locate the same RID in the second result.
             */
            $rids =
                $search->getRids();

            $matchedRow = null;

            foreach (
                $rows as $index => $candidate
            ) {

                if (
                    (int) (
                        $rids[$index] ?? 0
                    ) ===
                    (int) $record['rid']
                ) {

                    $matchedRow =
                        $candidate;

                    break;
                }
            }

            if ($matchedRow === null) {

                /*
                 * Fallback: the original row.
                 */
                $matchedRow =
                    $row;
            }

            $text =
                $this->expandContent(
                    $content,
                    $matchedRow,
                    $fieldIndexes,
                    $errortext
                );

            $this->renderContent(
                $text
            );

        } catch (Throwable $e) {

            $this->error(
                'PLM select: ' .
                $e->getMessage()
            );
        }
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
                '/\$(' .
                self::STATE_PATTERN .
                ')/',
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

    private function getStructFields(
        string $filter,
        string $content
    ): array {

        $fields = [];

        preg_match_all(
            '/(?:^|\(|\s|AND\s+|OR\s+)'
            . '('
            . self::FIELD_PATTERN
            . ')'
            . '\s*(?:=|!=|~|!~|=\*|>=|<=|>|<)/i',
            $filter,
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

        preg_match_all(
            '/\$(' .
            self::FIELD_PATTERN .
            ')/',
            $content,
            $matches
        );

        foreach (
            $matches[1] ?? []
            as $field
        ) {

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

    private function expandContent(
        string $content,
        ?array $row,
        array $fieldIndexes,
        string $errortext
    ): string {

        return
            preg_replace_callback(
                '/\$(' .
                self::FIELD_PATTERN .
                ')/',
                function ($match)
                    use (
                        $row,
                        $fieldIndexes,
                        $errortext
                    ) {

                    $field =
                        $match[1];

                    if ($row === null) {
                        return $errortext;
                    }

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

    private function renderContent(
        string $text
    ): void {

        if (trim($text) === '') {
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