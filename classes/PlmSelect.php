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
     * True when a PLM State/request reference used by the
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
     * Syntax:
     *
     *     /plm:select > view | plm_companies[_pk=%tablecompanies.current._pk] "<not found>"
     *     __Change : $name
     *     /plm
     *
     * Semantics:
     *
     *     view
     *         PLM State context in which the selected record is stored.
     *
     *     plm_companies
     *         Struct schema to search.
     *
     *     _pk=%tablecompanies.current._pk
     *         Struct filter. PLM references inside the filter are
     *         resolved before the Struct search.
     *
     *     $name
     *         Field from the Struct record found by the search.
     *
     * The selected record is:
     *
     *     1. found through PlmStruct
     *     2. attached as current Struct row to PlmReference
     *     3. stored as <context>.current in PlmState
     *     4. used to expand the select content
     */
    public function render(
        string $name,
        string $schema,
        string $filter,
        string $content,
        string $errortext = 'not found!'
    ): void {

        if ($name === '') {

            $this->error(
                'PLM select: context is required'
            );

            return;
        }

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
             * The select must resolve its filter without
             * inheriting a previous Struct row.
             *
             * Example:
             *
             *     %tablecompanies.current._pk
             *
             * must come from PLM State, not from the Struct
             * row currently attached to PlmReference.
             */
            $this->reference->clearRow();

            /*
             * Resolve PLM references inside the filter.
             *
             * Example:
             *
             *     _pk=%tablecompanies.current._pk
             *
             * becomes:
             *
             *     _pk=123
             */
            $expandedFilter =
                $this->expandFilter(
                    $filter
                );

            /*
             * A missing referenced value means there can be
             * no valid Struct record to select.
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
             * PlmStruct owns the actual Struct lookup.
             *
             * _pk is handled by PlmStruct::findByPrimaryKey().
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
             * PlmStruct already provides the field => row-index
             * mapping together with the returned record.
             *
             * This is important for records found through
             * findByPrimaryKey(), because those records do not
             * contain a SearchConfig object.
             */
            $fieldIndexes =
                $this->getFieldIndexes(
                    $record,
                    $row
                );

            /*
             * Attach the found Struct record as the current
             * Struct row.
             *
             * This is what makes:
             *
             *     $name
             *     $description
             *     $_pk
             *     $lookup._pk
             *
             * available to PlmReference.
             */
            $this->reference->setRow(
                $row,
                $fieldIndexes,
                $rid
            );

            /*
             * Store the same selected record in PLM State.
             *
             * For:
             *
             *     /plm:select > view | ...
             *
             * this creates:
             *
             *     %view.current._pk
             *     %view.current.<field>
             */
            $this->storeCurrentState(
                $name,
                $row,
                $fieldIndexes,
                $rid
            );

            /*
             * Expand the actual body of the select.
             *
             * Example:
             *
             *     __Change : $name
             *
             * becomes:
             *
             *     __Change : ACME
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
             * Never leave the Struct row attached after an error.
             */
            $this->reference->clearRow();

            /*
             * Also remove the State created by this select.
             */
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
     * Resolve references inside the Struct filter.
     *
     * Supported:
     *
     *     &_pk
     *     %table.current.field
     *
     * Examples:
     *
     *     _pk=%tablecompanies.current._pk
     *     ipn=%tableparts.current.ipn
     *     name=&name
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
         *     %table.current._pk
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
     *
     * This is intentionally done after reference resolution.
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
     * Determine the field => row-index mapping from the
     * PlmStruct result.
     *
     * PlmStruct::findOne() and PlmStruct::findByPrimaryKey()
     * already calculate and return:
     *
     *     'fieldIndexes' => [
     *         'fieldname' => row index,
     *         ...
     *     ]
     *
     * This must be preferred over deriving the mapping from
     * SearchConfig because findByPrimaryKey() intentionally
     * does not return the SearchConfig object.
     */
    private function getFieldIndexes(
        array $record,
        array $row
    ): array {

        $fieldIndexes =
            $record['fieldIndexes']
            ?? null;

        if (
            !is_array($fieldIndexes) ||
            empty($fieldIndexes)
        ) {

            throw new \RuntimeException(
                'PLM select: Struct field indexes are not available.'
            );
        }

        /*
         * Keep only mappings which actually point to a
         * value position in the returned row.
         */
        $result = [];

        foreach (
            $fieldIndexes as $field => $index
        ) {

            if (
                !is_string($field) ||
                $field === ''
            ) {
                continue;
            }

            if (
                !is_int($index) &&
                !ctype_digit(
                    (string) $index
                )
            ) {
                continue;
            }

            $index =
                (int) $index;

            if (
                !array_key_exists(
                    $index,
                    $row
                )
            ) {
                continue;
            }

            $result[$field] =
                $index;
        }

        if (empty($result)) {

            throw new \RuntimeException(
                'PLM select: could not map Struct result columns.'
            );
        }

        return $result;
    }

    /**
     * Store the selected Struct record in PLM State.
     *
     * Example:
     *
     *     context = view
     *
     * produces:
     *
     *     %view.current._pk
     *     %view.current.name
     *     %view.current.description
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
             * PLM State stores display values for normal fields.
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
     * Render expanded PLM content.
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

