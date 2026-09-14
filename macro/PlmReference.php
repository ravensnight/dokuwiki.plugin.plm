<?php

class PlmReference
{
    public const TYPE_REQUEST = 'request';
    public const TYPE_STRUCT = 'struct';
    public const TYPE_STATE = 'state';
    public const TYPE_TEMPLATE = 'template';

    private PlmState $state;

    private ?PlmStruct $struct;

    private ?array $row = null;

    private array $fieldIndexes = [];

    private ?int $rowRid = null;

    private array $templates = [];

    public function __construct(
        PlmState $state,
        ?PlmStruct $struct = null
    ) {
        $this->state = $state;
        $this->struct = $struct;
    }

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

    public function clearRow(): void
    {
        $this->row = null;
        $this->fieldIndexes = [];
        $this->rowRid = null;
    }

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

            if (!is_string($template)) {
                continue;
            }

            $this->templates[$name] =
                $template;
        }
    }

    public function setTemplate(
        string $name,
        string $template
    ): void {

        if (!$this->isValidName($name)) {
            return;
        }

        $this->templates[$name] =
            $template;
    }

    public function clearTemplate(
        string $name
    ): void {

        if (!$this->isValidName($name)) {
            return;
        }

        unset($this->templates[$name]);
    }

    public function getTemplates(): array
    {
        return $this->templates;
    }

    public function resolve(
        string $reference
    ): ?string {

        $reference =
            trim($reference);

        if ($reference === '') {
            return null;
        }

        if ($reference[0] === '&') {
            return $this->resolveRequest(
                substr($reference, 1)
            );
        }

        if ($reference[0] === '$') {
            return $this->resolveStruct(
                substr($reference, 1)
            );
        }

        if ($reference[0] === '%') {
            return $this->resolveState(
                substr($reference, 1)
            );
        }

        if ($reference[0] === '@') {
            return $this->resolveTemplate(
                substr($reference, 1)
            );
        }

        return null;
    }

    private function resolveRequest(
        string $name
    ): ?string {

        if (!$this->isValidName($name)) {
            return null;
        }

        global $INPUT;

        if (
            isset($INPUT) &&
            is_object($INPUT)
        ) {

            try {

                $value =
                    $INPUT->str($name);

                if ($value !== null) {
                    return (string) $value;
                }

            } catch (Throwable $e) {
            }

            if (isset($INPUT->get)) {

                try {

                    $value =
                        $INPUT->get->str($name);

                    if ($value !== null) {
                        return (string) $value;
                    }

                } catch (Throwable $e) {
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
     * Resolve a reference against the current Struct row.
     *
     * $field
     *     -> display value
     *
     * $field._pk
     *     -> raw Lookup RID
     *
     * $_pk
     *     -> current row RID
     */
    private function resolveStruct(
        string $reference
    ): ?string {

        if (
            $reference ===
            PlmStruct::PRIMARY_KEY_FIELD
        ) {

            if ($this->rowRid === null) {
                return null;
            }

            return (string) $this->rowRid;
        }

        if ($this->row === null) {
            return null;
        }

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

            if (!$this->isValidName($field)) {
                return null;
            }

            return $this->resolveLookupRid(
                $field
            );
        }

        if (!$this->isValidName($reference)) {
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

        /*
         * Normal Struct references are display-oriented.
         *
         * This is deliberately different from PLM State.
         */
        return $this->stringifyStructDisplayValue(
            $this->row[$index]
        );
    }

    /**
     * Resolve the first RID of a Struct Lookup field.
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

        if ($this->struct === null) {
            return null;
        }

        $rids =
            $this->struct->getLookupPrimaryKeys(
                $value
            );

        if (empty($rids)) {
            return null;
        }

        return (string) $rids[0];
    }

    /**
     * Resolve PLM State.
     *
     * IMPORTANT:
     *
     * State contains raw values.
     * No Struct display conversion takes place here.
     */
    private function resolveState(
        string $path
    ): ?string {

        $parts =
            explode(
                '.',
                $path
            );

        if (count($parts) !== 3) {
            return null;
        }

        [
            $context,
            $scope,
            $field
        ] = $parts;

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

    private function resolveTemplate(
        string $name
    ): ?string {

        if (!$this->isValidName($name)) {
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

    private function expandRecursive(
        string $text,
        bool $keepUnresolved,
        array $templateStack
    ): string {

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

                    if ($value === null) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    return $value;
                },
                $text
            );

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

                    if ($value === null) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    return $value;
                },
                $text
            );

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

                    if ($value === null) {
                        return $keepUnresolved
                            ? $match[0]
                            : '';
                    }

                    return $value;
                },
                $text
            );

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

                    if ($template === null) {
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
     * Convert a Struct Value into its display value.
     *
     * This is ONLY used for live Struct row references.
     * It is never used when reading PLM State.
     */
    private function stringifyStructDisplayValue(
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

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function isValidName(
        string $name
    ): bool {

        return preg_match(
            '/^[a-zA-Z0-9_-]+$/',
            $name
        ) === 1;
    }
}