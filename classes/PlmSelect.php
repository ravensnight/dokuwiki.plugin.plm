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

    /**
     * True when a PLM state/request reference used by the
     * filter exists but contains no value.
     */
    private bool $filterStateMissing = false;

    /**
     * CSS class for a normal "nothing found" message.
     */
    private const EMPTY_CLASS = 'plm_empty';

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
     * Render a PLM select.
     *
     * The select:
     *
     *  1. resolves the filter through PlmReference
     *  2. therefore uses PlmState / request references
     *  3. finds exactly one Struct record through PlmStruct
     *  4. attaches the Struct row to PlmReference
     *  5. stores the selected record in PlmState
     *  6. expands the content through PlmReference
     *
     * Example:
     *
     *     /plm:select > view |
     *         plm_companies
     *         _pk=%tablecompanies.current._pk
     *         "<not found>"
     *     /plm
     *
     * Content may contain:
     *
     *     $name
     *     $description
     *     $_pk
     *     $part._pk
     *     %other.current.value
     *     @template
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

        try {

            /*
             * Select must not inherit a previous Struct row
             * while resolving its filter.
             *
             * This is important because the filter itself
             * may use PLM State:
             *
             *     %tablecompanies.current._pk
             *
             * State resolution is independent from the
             * current Struct row.
             */
            $this->reference->clearRow();

            /*
             * Resolve the filter exactly like PlmForm does.
             *
             * This resolves:
             *
             *     &_pk
             *     %context.filter.field
             *     %context.current.field
             *
             * through PlmReference.
             */
            $expandedFilter =
                $this->expandFilter(
                    $filter
                );

            /*
             * A missing State/request value means that there
             * cannot be a matching record.
             */
            if ($this->filterStateMissing) {

                $this->renderEmpty(
                    $errortext
                );

                return;
            }

            if (
                $expandedFilter === null ||
                trim($expandedFilter) === ''
            ) {

                $this->renderEmpty(
                    $errortext
                );

                return;
            }

            /*
             * Find the record.
             *
             * From this point onward PlmStruct is the only
             * component responsible for Struct lookup.
             *
             * No SearchConfig is created by PlmSelect.
             */
            $record =
                $this->struct->findOne(
                    $schema,
                    $expandedFilter
                );

            if ($record === null) {

                $this->renderEmpty(
                    $errortext
                );

                return;
            }

            $row =
                $record['row']
                ?? null;

            $rid =
                (int) (
                    $record['rid']
                    ?? 0
                );

            if (
                !is_array($row) ||
                $rid <= 0
            ) {

                $this->renderEmpty(
                    $errortext
                );

                return;
            }

            /*
             * Build the field => column-index mapping from
             * the Struct SearchConfig that produced the row.
             *
             * PlmStruct::findOne() deliberately returns only
             * the row itself, therefore we obtain the columns
             * through the same schema definition.
             *
             * The important point is that this is NOT used
             * to perform another search.
             */
            $fieldIndexes =
                $this->getFieldIndexes(
                    $schema,
                    $row
                );

            /*
             * Make the found Struct row current.
             *
             * From this point:
             *
             *     $name
             *     $description
             *     $type
             *     $_pk
             *     $lookup._pk
             *
             * are resolved by PlmReference.
             */
            $this->reference->setRow(
                $row,
                $fieldIndexes,
                $rid
            );

            /*
             * Store the selected record in PLM State.
             *
             * This creates:
             *
             *     %<name>.current._pk
             *     %<name>.current.<field>
             */
            $this->storeCurrentState(
                $name,
                $row,
                $fieldIndexes,
                $rid
            );

            /*
             * Now the Struct row is attached to PlmReference
             * and State contains the selected record.
             *
             * Therefore the content may use both:
             *
             *     $field
             *
             * and:
             *
             *     %context.current.field
             *
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

            /*
             * Never leave the selected Struct row attached
             * after an error.
             */
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
     * Expand the select filter through PlmReference.
     *
     * This intentionally follows the same architecture
     * as PlmForm.
     */
    private function expandFilter(
        string $filter
    ): ?string {

        $this->filterStateMissing = false;

        $filter =
            trim(
                $filter
            );

        if ($filter === '') {
            return null;
        }

        /*
         * Request references.
         *
         * Example:
         *
         *     &_pk
         */
        $filter =
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match) {

                    $value =
                        $this->reference->resolve(
                            '&' . $match[1]
                        );

                    if (
                        $value === null ||
                        $value === ''
                    ) {

                        $this->filterStateMissing =
                            true;

                        return '';
                    }

                    return $this->escapeFilterValue(
                        $value
                    );
                },
                $filter
            );

        if ($this->filterStateMissing) {
            return null;
        }

        /*
         * PLM State references.
         *
         * Example:
         *
         *     %tablecompanies.current._pk
         *
         * or:
         *
         *     %tablecompanies.current.ipn
         */
        $filter =
            preg_replace_callback(
                '/%([a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+)/',
                function ($match) {

                    $value =
                        $this->reference->resolve(
                            '%' . $match[1]
                        );

                    if (
                        $value === null ||
                        $value === ''
                    ) {

                        $this->filterStateMissing =
                            true;

                        return '';
                    }

                    return $this->escapeFilterValue(
                        $value
                    );
                },
                $filter
            );

        if ($this->filterStateMissing) {
            return null;
        }

        return trim($filter);
    }

    /**
     * Escape a value inserted into a Struct filter.
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
     * Determine the column indexes of the returned Struct row.
     *
     * IMPORTANT:
     *
     * This does not perform a Struct search.
     *
     * The indexes are derived from the schema columns so the
     * already returned row can be connected to PlmReference.
     */
    private function getFieldIndexes(
        string $schema,
        array $row
    ): array {

        $schemaObject =
            new \dokuwiki\plugin\struct\meta\Schema(
                $schema
            );

        if (!$schemaObject->getId()) {

            throw new \RuntimeException(
                'Struct schema does not exist: ' .
                $schema
            );
        }

        /*
         * SearchConfig rows contain values in the same column
         * order as the SearchConfig columns.
         *
         * getColumns() provides that order.
         */
        $columns =
            $schemaObject->getColumns();

        if (empty($columns)) {

            throw new \RuntimeException(
                'Struct schema contains no fields: ' .
                $schema
            );
        }

        $fieldIndexes = [];

        /*
         * The row returned by SearchConfig contains exactly
         * the requested columns, not necessarily every schema
         * column.
         *
         * Therefore we need to determine the actual columns
         * from the row's value objects where possible.
         *
         * For normal Struct fields the schema order is used.
         */
        $index = 0;

        foreach (
            $columns as $column
        ) {

            if (
                !array_key_exists(
                    $index,
                    $row
                )
            ) {
                break;
            }

            $fieldIndexes[
                $column->getLabel()
            ] = $index;

            $index++;
        }

        /*
         * If the returned row has more entries than could be
         * mapped through the schema columns, do not invent
         * field names.
         */
        if (empty($fieldIndexes)) {

            throw new \RuntimeException(
                'Could not determine Struct column indexes.'
            );
        }

        return $fieldIndexes;
    }

    /**
     * Store the selected Struct row in PLM State.
     *
     * Result:
     *
     *     %<context>.current._pk
     *     %<context>.current.<field>
     */
    private function storeCurrentState(
        string $context,
        array $row,
        array $fieldIndexes,
        int $rid
    ): void {

        $values = [];

        /*
         * Always expose the technical Struct RID.
         */
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

            /*
             * Struct Value objects may provide a display value.
             *
             * State intentionally stores the display value here,
             * just as PlmForm/PLM State expects.
             */
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
            $context,
            'current',
            $values
        );
    }

    /**
     * Render the normal "nothing found" message.
     */
    private function renderEmpty(
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
            '<div class="' .
            self::EMPTY_CLASS .
            '">' .
            $html .
            '</div>';
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