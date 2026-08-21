<?php

class PlmSelect
{
    /** @var Doku_Renderer */
    private $renderer;

    /** @var PlmStruct */
    private $struct;

    /** @var PlmState */
    private $state;

    /** @var PlmReference */
    private $reference;

    public function __construct(
        Doku_Renderer $renderer,
        PlmStruct $struct,
        PlmState $state,
        PlmReference $reference
    ) {
        $this->renderer = $renderer;
        $this->struct = $struct;
        $this->state = $state;
        $this->reference = $reference;
    }

    /**
     * Render the PLM select.
     *
     * The select finds exactly one Struct record and
     * renders its content.
     *
     * The selected record is additionally stored in:
     *
     *     <name>.current.*
     */
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
         * No current Struct row exists while resolving
         * the select filter.
         */
        $this->reference->clearRow();

        /*
         * Resolve request and state references.
         *
         * Example:
         *
         *     _pk=&_pk
         *
         * becomes:
         *
         *     _pk=123
         */
        $expandedFilter =
            $this->reference->expand(
                $filter,
                true
            );

        /*
         * A Struct reference such as:
         *
         *     $field
         *
         * cannot be resolved before the record exists.
         */
        if (
            $this->containsStructReference(
                $expandedFilter
            )
        ) {

            $this->renderContent(
                $this->replaceUnresolvedWithError(
                    $content,
                    $errortext
                )
            );

            return;
        }

        try {

            /*
             * findOne() is deliberately used here instead
             * of calling search() directly.
             *
             * PlmStruct::findOne() knows how to handle:
             *
             *     _pk=123
             *
             * by forwarding it to:
             *
             *     findByPrimaryKey()
             */
            $record =
                $this->struct->findOne(
                    $schema,
                    $expandedFilter
                );

            if ($record === null) {

                $this->renderContent(
                    $this->replaceUnresolvedWithError(
                        $content,
                        $errortext
                    )
                );

                return;
            }

            $row =
                $record['row'];

            $rid =
                (int) (
                    $record['rid'] ?? 0
                );

            /*
             * findOne() may return a row containing only
             * the field used by the search.
             *
             * For rendering the content we need all Struct
             * fields referenced by the content.
             */
            $fields =
                $this->getStructFields(
                    $expandedFilter,
                    $content
                );

            /*
             * If no explicit Struct fields are required,
             * the row returned by findOne() is sufficient.
             */
            if (!empty($fields)) {

                /*
                 * IMPORTANT:
                 *
                 * _pk must never be passed to search().
                 *
                 * It is a PLM pseudo field and has already
                 * been resolved by findOne().
                 */
                $fields =
                    array_values(
                        array_filter(
                            $fields,
                            function ($field) {

                                return
                                    $field !==
                                    PlmStruct::PRIMARY_KEY_FIELD;
                            }
                        )
                    );

                if (!empty($fields)) {

                    /*
                     * We cannot search using _pk again.
                     *
                     * Instead, retrieve the requested fields
                     * without a _pk filter and select the row
                     * with the already known RID.
                     */
                    $result =
                        $this->struct->search(
                            $schema,
                            $fields
                        );

                    $search =
                        $result['search'];

                    $rows =
                        $result['rows'];

                    $rids =
                        $search->getRids();

                    $matchedRow = null;

                    foreach (
                        $rows as $index => $candidate
                    ) {

                        if (
                            (int) (
                                $rids[$index] ?? 0
                            ) === $rid
                        ) {

                            $matchedRow =
                                $candidate;

                            break;
                        }
                    }

                    if ($matchedRow !== null) {

                        $row =
                            $matchedRow;
                    }
                }
            }

            /*
             * Build field => column index map.
             *
             * findOne() alone does not expose Struct
             * column metadata, so we need a search result
             * for that.
             */
            $fieldIndexes = [];

            if (!empty($fields)) {

                $result =
                    $this->struct->search(
                        $schema,
                        $fields
                    );

                $search =
                    $result['search'];

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
                 * The previous search may have returned the
                 * row in a different order. Find it again by RID.
                 */
                $rids =
                    $search->getRids();

                $rows =
                    $result['rows'];

                foreach (
                    $rows as $index => $candidate
                ) {

                    if (
                        (int) (
                            $rids[$index] ?? 0
                        ) === $rid
                    ) {

                        $row =
                            $candidate;

                        break;
                    }
                }
            }

            /*
             * Make the selected Struct row available to
             * PlmReference.
             *
             * Content can now use:
             *
             *     $ipn
             *     $_pk
             *     $part._pk
             *     %context.current.ipn
             *     @template
             */
            $this->reference->setRow(
                $row,
                $fieldIndexes
            );

            /*
             * Store the selected record in PLM state.
             */
            $this->storeCurrentState(
                $name,
                $row,
                $fieldIndexes,
                $rid
            );

            /*
             * Expand content through the common reference
             * resolver.
             */
            $text =
                $this->reference->expand(
                    $content,
                    false
                );

            $this->renderContent(
                $text
            );

        } catch (Throwable $e) {

            $this->reference->clearRow();

            $this->state->clearContext(
                $name
            );

            $this->error(
                'PLM select: ' .
                $e->getMessage()
            );
        }
    }

    /**
     * Store the selected Struct row in PLM state.
     */
    private function storeCurrentState(
        string $name,
        array $row,
        array $fieldIndexes,
        int $rid
    ): void {

        $values = [];

        if ($rid > 0) {

            $values[
                PlmStruct::PRIMARY_KEY_FIELD
            ] =
                (string) $rid;
        }

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

            if (
                method_exists(
                    $value,
                    'getDisplayValue'
                )
            ) {

                $display =
                    $value->getDisplayValue();

                if (
                    $display === null ||
                    $display === ''
                ) {
                    continue;
                }

                $values[$field] =
                    (string) $display;

                continue;
            }

            if (is_scalar($value)) {

                $string =
                    (string) $value;

                if ($string !== '') {

                    $values[$field] =
                        $string;
                }
            }
        }

        $this->state->setScope(
            $name,
            'current',
            $values
        );
    }

    /**
     * Determine Struct fields required by the filter
     * and content.
     */
    private function getStructFields(
        string $filter,
        string $content
    ): array {

        $fields = [];

        /*
         * Fields used directly by the Struct filter.
         */
        preg_match_all(
            '/(?:^|\(|\s|AND\s+|OR\s+)'
            . '('
            . '[a-zA-Z0-9]+'
            . '(?:[_.-][a-zA-Z0-9]+)*'
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

            if ($field !== '') {

                $fields[] =
                    $field;
            }
        }

        /*
         * Struct references used by content.
         *
         *     $ipn
         *     $_pk
         *     $part._pk
         */
        preg_match_all(
            '/\$([a-zA-Z0-9_-]+(?:\._pk)?)/',
            $content,
            $matches
        );

        foreach (
            $matches[1] ?? []
            as $reference
        ) {

            if (
                str_ends_with(
                    $reference,
                    '._pk'
                )
            ) {

                $field =
                    substr(
                        $reference,
                        0,
                        -4
                    );

                if ($field !== '') {

                    $fields[] =
                        $field;
                }

                continue;
            }

            if (
                $reference ===
                PlmStruct::PRIMARY_KEY_FIELD
            ) {
                continue;
            }

            $fields[] =
                $reference;
        }

        return array_values(
            array_unique(
                $fields
            )
        );
    }

    /**
     * Check for unresolved current Struct references.
     */
    private function containsStructReference(
        string $text
    ): bool {

        return preg_match(
            '/\$([a-zA-Z0-9_-]+(?:\._pk)?)/',
            $text
        ) === 1;
    }

    /**
     * Replace unresolved references in content with
     * the configured error text.
     */
    private function replaceUnresolvedWithError(
        string $content,
        string $errortext
    ): string {

        return preg_replace_callback(
            '/(?:&[a-zA-Z0-9_-]+'
            . '|\$[a-zA-Z0-9_-]+(?:\._pk)?'
            . '|%[a-zA-Z0-9_-]+'
            . '\.[a-zA-Z0-9_-]+'
            . '\.[a-zA-Z0-9_.-]+'
            . '|@[a-zA-Z0-9_-]+)/',
            function () use ($errortext) {

                return $errortext;
            },
            $content
        );
    }

    /**
     * Render PLM content.
     */
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

    /**
     * Display a technical error.
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