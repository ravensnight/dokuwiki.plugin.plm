<?php

class PlmReference
{
    /**
     * Reference types.
     */
    public const TYPE_REQUEST = 'request';

    public const TYPE_STRUCT = 'struct';

    public const TYPE_STATE = 'state';

    public const TYPE_TEMPLATE = 'template';

    /**
     * @var PlmState
     */
    private PlmState $state;

    /**
     * @var PlmStruct|null
     */
    private ?PlmStruct $struct;

    /**
     * Current Struct row.
     *
     * Contains Struct Value objects indexed by their
     * Struct column position.
     */
    private ?array $row = null;

    /**
     * Current Struct field indexes.
     */
    private array $fieldIndexes = [];

    /**
     * Current Struct row RID.
     */
    private ?int $rowRid = null;

    /**
     * Available templates.
     *
     * Example:
     *
     * [
     *     'details' => 'intern:plm:details:$category:$part._pk',
     * ]
     */
    private array $templates = [];

    public function __construct(
        PlmState $state,
        ?PlmStruct $struct = null
    ) {
        $this->state = $state;
        $this->struct = $struct;
    }

    /**
     * Set the current Struct row.
     */
    public function setRow(
        ?array $row,
        array $fieldIndexes = [],
        ?int $rid = null
    ): void {

        $this->row =
            $row;

        $this->fieldIndexes =
            $fieldIndexes;

        $this->rowRid =
            $rid;
    }

    /**
     * Clear the current Struct row.
     */
    public function clearRow(): void
    {
        $this->row = null;
        $this->fieldIndexes = [];
        $this->rowRid = null;
    }

    /**
     * Set all available templates.
     *
     * Template names are referenced with:
     *
     *     @details
     */
    public function setTemplates(
        array $templates
    ): void {

        $this->templates = [];

        foreach (
            $templates as $name => $template
        ) {

            if (
                !is_string($name) ||
                !$this->isValidName($name)
            ) {
                continue;
            }

            if (
                !is_string($template)
            ) {
                continue;
            }

            $this->templates[$name] =
                $template;
        }
    }

    /**
     * Add or replace one template.
     */
    public function setTemplate(
        string $name,
        string $template
    ): void {

        if (
            !$this->isValidName($name)
        ) {
            return;
        }

        $this->templates[$name] =
            $template;
    }

    /**
     * Remove one template.
     */
    public function clearTemplate(
        string $name
    ): void {

        if (
            !$this->isValidName($name)
        ) {
            return;
        }

        unset(
            $this->templates[$name]
        );
    }

    /**
     * Get all registered templates.
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Resolve a complete reference.
     *
     * Supported:
     *
     *     &_pk
     *     $ipn
     *     $_pk
     *     $part._pk
     *     %mycontext.filter.ipn
     *     %mycontext.current._pk
     *     @details
     */
    public function resolve(
        string $reference
    ): ?string {

        $reference =
            trim(
                $reference
            );

        if ($reference === '') {
            return null;
        }

        if (
            $reference[0] === '&'
        ) {

            return $this->resolveRequest(
                substr(
                    $reference,
                    1
                )
            );
        }

        if (
            $reference[0] === '$'
        ) {

            return $this->resolveStruct(
                substr(
                    $reference,
                    1
                )
            );
        }

        if (
            $reference[0] === '%'
        ) {

            return $this->resolveState(
                substr(
                    $reference,
                    1
                )
            );
        }

        if (
            $reference[0] === '@'
        ) {

            return $this->resolveTemplate(
                substr(
                    $reference,
                    1
                )
            );
        }

        return null;
    }

    /**
     * Resolve a request reference.
     */
    private function resolveRequest(
        string $name
    ): ?string {

        if (
            !$this->isValidName($name)
        ) {
            return null;
        }

        global $INPUT;

        if (
            isset($INPUT) &&
            is_object($INPUT)
        ) {

            try {

                $value =
                    $INPUT->str(
                        $name
                    );

                if ($value !== null) {
                    return (string) $value;
                }

            } catch (Throwable $e) {
                // Fall through.
            }

            if (
                isset($INPUT->get)
            ) {

                try {

                    $value =
                        $INPUT->get->str(
                            $name
                        );

                    if ($value !== null) {
                        return (string) $value;
                    }

                } catch (Throwable $e) {
                    // Fall through.
                }
            }
        }

        if (
            isset($_GET[$name]) &&
            !is_array($_GET[$name]) &&
            !is_object($_GET[$name])
        ) {

            return (string) $_GET[$name];
        }

        return null;
    }

    /**
     * Resolve a current Struct reference.
     *
     *     $ipn
     *     $_pk
     *     $part._pk
     */
    private function resolveStruct(
        string $reference
    ): ?string {

        /*
         * Primary key of the current Struct row.
         *
         * _pk is not a normal Struct column and therefore
         * is not present in $fieldIndexes.
         */
        if (
            $reference ===
            PlmStruct::PRIMARY_KEY_FIELD
        ) {

            if ($this->rowRid === null) {
                return null;
            }

            return (string) $this->rowRid;
        }

        if (
            $this->row === null
        ) {
            return null;
        }

        /*
         * Lookup RID:
         *
         *     $part._pk
         */
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

            if (
                !$this->isValidName($field)
            ) {
                return null;
            }

            return $this->resolveLookupRid(
                $field
            );
        }

        /*
         * Normal current-row field:
         *
         *     $ipn
         */
        if (
            !$this->isValidName($reference)
        ) {
            return null;
        }

        if (
            !array_key_exists(
                $reference,
                $this->fieldIndexes
            )
        ) {
            return null;
        }

        $index =
            $this->fieldIndexes[$reference];

        if (
            !array_key_exists(
                $index,
                $this->row
            )
        ) {
            return null;
        }

        return $this->stringifyStructValue(
            $this->row[$index]
        );
    }

    /**
     * Resolve the RID of a Lookup field.
     */
    private function resolveLookupRid(
        string $field
    ): ?string {

        if (
            !array_key_exists(
                $field,
                $this->fieldIndexes
            )
        ) {
            return null;
        }

        $index =
            $this->fieldIndexes[$field];

        if (
            !array_key_exists(
                $index,
                $this->row ?? []
            )
        ) {
            return null;
        }

        $value =
            $this->row[$index];

        if (
            $this->struct === null
        ) {
            return null;
        }

        $rids =
            $this->struct->getLookupPrimaryKeys(
                $value
            );

        if (
            empty($rids)
        ) {
            return null;
        }

        return (string) $rids[0];
    }

    /**
     * Resolve a PLM state reference.
     *
     *     %mycontext.filter.ipn
     *     %mycontext.current._pk
     */
    private function resolveState(
        string $path
    ): ?string {

        $parts =
            explode(
                '.',
                $path
            );

        if (
            count($parts) !== 3
        ) {
            return null;
        }

        [
            $context,
            $scope,
            $field
        ] =
            $parts;

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope) ||
            !$this->isValidName($field)
        ) {
            return null;
        }

        return $this->state->getValue(
            $context,
            $scope,
            $field
        );
    }

    /**
     * Resolve a template reference.
     *
     *     @details
     */
    private function resolveTemplate(
        string $name
    ): ?string {

        if (
            !$this->isValidName($name)
        ) {
            return null;
        }

        if (
            !array_key_exists(
                $name,
                $this->templates
            )
        ) {
            return null;
        }

        return $this->templates[$name];
    }

    /**
     * Expand all references inside arbitrary text.
     *
     * Examples:
     *
     *     intern:plm:details:$category:$part._pk
     *
     *     intern:plm:details:%mycontext.current._pk
     *
     *     @details
     */
    public function expand(
        string $text,
        bool $keepUnresolved = true
    ): string {

        return $this->expandRecursive(
            $text,
            $keepUnresolved,
            []
        );
    }

    /**
     * Recursive expansion implementation.
     */
    private function expandRecursive(
        string $text,
        bool $keepUnresolved,
        array $templateStack
    ): string {

        /*
         * Request references.
         */
        $text =
            preg_replace_callback(
                '/&([a-zA-Z0-9_-]+)/',
                function ($match)
                    use ($keepUnresolved) {

                    $reference =
                        '&' . $match[1];

                    $value =
                        $this->resolve(
                            $reference
                        );

                    if (
                        $value === null
                    ) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    return $value;
                },
                $text
            );

        /*
         * Struct references.
         *
         * $part._pk must be matched as one
         * reference before normal $field.
         */
        $text =
            preg_replace_callback(
                '/\$([a-zA-Z0-9_-]+(?:\._pk)?)/',
                function ($match)
                    use ($keepUnresolved) {

                    $reference =
                        '$' . $match[1];

                    $value =
                        $this->resolve(
                            $reference
                        );

                    if (
                        $value === null
                    ) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    return $value;
                },
                $text
            );

        /*
         * PLM state references.
         *
         *     %context.scope.field
         */
        $text =
            preg_replace_callback(
                '/%([a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+'
                . '\.[a-zA-Z0-9_-]+)/',
                function ($match)
                    use ($keepUnresolved) {

                    $reference =
                        '%' . $match[1];

                    $value =
                        $this->resolve(
                            $reference
                        );

                    if (
                        $value === null
                    ) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    return $value;
                },
                $text
            );

        /*
         * Template references.
         *
         * Resolve the template itself and then
         * recursively expand its contents.
         */
        $text =
            preg_replace_callback(
                '/@([a-zA-Z0-9_-]+)/',
                function ($match)
                    use (
                        $keepUnresolved,
                        $templateStack
                    ) {

                    $name =
                        $match[1];

                    /*
                     * Detect template recursion.
                     *
                     * @a -> @b -> @a
                     */
                    if (
                        in_array(
                            $name,
                            $templateStack,
                            true
                        )
                    ) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    $template =
                        $this->resolve(
                            '@' . $name
                        );

                    if (
                        $template === null
                    ) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    $templateStack[] =
                        $name;

                    return $this->expandRecursive(
                        $template,
                        $keepUnresolved,
                        $templateStack
                    );
                },
                $text
            );

        return $text;
    }

    /**
     * Convert a Struct Value into a scalar string.
     */
    private function stringifyStructValue(
        $value
    ): ?string {

        if ($value === null) {
            return null;
        }

        if (
            method_exists(
                $value,
                'getDisplayValue'
            )
        ) {

            $display =
                $value->getDisplayValue();

            if ($display === null) {
                return null;
            }

            return (string) $display;
        }

        if (
            is_scalar($value)
        ) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Validate a reference name.
     *
     * Dots are deliberately excluded because they
     * have structural meaning in references.
     */
    private function isValidName(
        string $name
    ): bool {

        return preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $name
        ) === 1;
    }
}