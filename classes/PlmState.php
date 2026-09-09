<?php

class PlmState
{
    /**
     * URL parameter containing the complete PLM state.
     */
    private const PARAMETER = 'plm';

    /**
     * Internal state.
     *
     * Structure:
     *
     *     [
     *         'mycontext' => [
     *             'filter' => [
     *                 'ipn' => '123',
     *             ],
     *             'current' => [
     *                 '_pk' => '42',
     *                 'ipn' => '123',
     *             ],
     *         ],
     *     ]
     */
    private array $state = [];

    public function __construct()
    {
        $this->loadState();
    }

    /**
     * Load the encoded PLM state.
     *
     * This deliberately does not assume that the global
     * DokuWiki INPUT object is always available.
     */
    private function loadState(): void
    {
        global $INPUT;

        $encoded = '';

        /*
         * Normal DokuWiki GET request.
         */
        if (
            isset($INPUT) &&
            is_object($INPUT) &&
            isset($INPUT->get)
        ) {
            try {

                $encoded =
                    $INPUT->get->str(
                        self::PARAMETER
                    );

            } catch (Throwable $e) {

                $encoded = '';
            }
        }

        /*
         * Fallback for environments where DokuWiki's
         * Input object is not available.
         */
        if (
            $encoded === '' &&
            isset($_GET[self::PARAMETER])
        ) {
            $encoded =
                is_string(
                    $_GET[self::PARAMETER]
                )
                    ? $_GET[self::PARAMETER]
                    : '';
        }

        /*
         * Form submissions carry the PLM state as a
         * hidden POST field because the form action itself
         * does not contain ?plm=...
         */
        if ($encoded === '') {

            if (
                isset($INPUT) &&
                is_object($INPUT) &&
                isset($INPUT->post)
            ) {
                try {

                    $encoded =
                        $INPUT->post->str(
                            self::PARAMETER
                        );

                } catch (Throwable $e) {

                    $encoded = '';
                }
            }
        }

        /*
         * Fallback for environments where DokuWiki's
         * Input object is not available.
         */
        if (
            $encoded === '' &&
            isset($_POST[self::PARAMETER])
        ) {
            $encoded =
                is_string(
                    $_POST[self::PARAMETER]
                )
                    ? $_POST[self::PARAMETER]
                    : '';
        }

        if (
            $encoded === null ||
            $encoded === ''
        ) {
            return;
        }

        $this->decode(
            (string) $encoded
        );
    }

    /**
     * Get the complete state.
     */
    public function get(): array
    {
        return $this->state;
    }

    /**
     * Get one context.
     *
     * Example:
     *
     *     $state->getContext('mycontext')
     *
     * returns:
     *
     *     [
     *         'filter' => [
     *             'ipn' => '123',
     *         ],
     *         'current' => [
     *             '_pk' => '42',
     *             'ipn' => '123',
     *         ],
     *     ]
     */
    public function getContext(
        string $context
    ): array {

        if (!$this->isValidName($context)) {
            return [];
        }

        $value =
            $this->state[$context]
            ?? [];

        return is_array($value)
            ? $value
            : [];
    }

    /**
     * Get one scope from a context.
     *
     * Example:
     *
     *     $state->getScope(
     *         'mycontext',
     *         'filter'
     *     );
     */
    public function getScope(
        string $context,
        string $scope
    ): array {

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope)
        ) {
            return [];
        }

        $value =
            $this->state[$context][$scope]
            ?? [];

        return is_array($value)
            ? $value
            : [];
    }

    /**
     * Get one state value.
     *
     * The state hierarchy is:
     *
     *     context.scope.field
     *
     * Example:
     *
     *     $state->getValue(
     *         'mycontext',
     *         'current',
     *         'ipn'
     *     );
     */
    public function getValue(
        string $context,
        string $scope,
        string $field
    ): ?string {

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope) ||
            !$this->isValidName($field)
        ) {
            return null;
        }

        if (
            !isset(
                $this->state[$context][$scope]
            ) ||
            !is_array(
                $this->state[$context][$scope]
            )
        ) {
            return null;
        }

        if (
            !array_key_exists(
                $field,
                $this->state[$context][$scope]
            )
        ) {
            return null;
        }

        $value =
            $this->state[$context][$scope][$field];

        if ($value === null) {
            return null;
        }

        if (
            is_array($value) ||
            is_object($value)
        ) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Set one state value.
     *
     * Empty values are removed.
     */
    public function setValue(
        string $context,
        string $scope,
        string $field,
        string $value
    ): void {

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope) ||
            !$this->isValidName($field)
        ) {
            return;
        }

        if ($value === '') {

            $this->clearValue(
                $context,
                $scope,
                $field
            );

            return;
        }

        if (
            !isset(
                $this->state[$context]
            ) ||
            !is_array(
                $this->state[$context]
            )
        ) {
            $this->state[$context] = [];
        }

        if (
            !isset(
                $this->state[$context][$scope]
            ) ||
            !is_array(
                $this->state[$context][$scope]
            )
        ) {
            $this->state[$context][$scope] = [];
        }

        $this->state[$context][$scope][$field] =
            $value;
    }

    /**
     * Clear one state value.
     */
    public function clearValue(
        string $context,
        string $scope,
        string $field
    ): void {

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope) ||
            !$this->isValidName($field)
        ) {
            return;
        }

        if (
            !isset(
                $this->state[$context][$scope]
            ) ||
            !is_array(
                $this->state[$context][$scope]
            )
        ) {
            return;
        }

        unset(
            $this->state[$context][$scope][$field]
        );

        /*
         * Remove empty scope.
         */
        if (
            empty(
                $this->state[$context][$scope]
            )
        ) {
            unset(
                $this->state[$context][$scope]
            );
        }

        /*
         * Remove empty context.
         */
        if (
            empty(
                $this->state[$context]
            )
        ) {
            unset(
                $this->state[$context]
            );
        }
    }

    /**
     * Replace an entire scope.
     *
     * This is useful for current-row state.
     *
     * Example:
     *
     *     $state->setScope(
     *         'mycontext',
     *         'current',
     *         [
     *             '_pk' => '42',
     *             'ipn' => '123',
     *             'name' => 'Part A',
     *         ]
     *     );
     */
    public function setScope(
        string $context,
        string $scope,
        array $values
    ): void {

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope)
        ) {
            return;
        }

        $clean = [];

        foreach (
            $values as $field => $value
        ) {

            if (
                !is_string($field) ||
                !$this->isValidName($field)
            ) {
                continue;
            }

            if (
                $value === null ||
                is_array($value) ||
                is_object($value)
            ) {
                continue;
            }

            $value =
                (string) $value;

            if ($value === '') {
                continue;
            }

            $clean[$field] =
                $value;
        }

        if (empty($clean)) {

            $this->clearScope(
                $context,
                $scope
            );

            return;
        }

        if (
            !isset(
                $this->state[$context]
            ) ||
            !is_array(
                $this->state[$context]
            )
        ) {
            $this->state[$context] = [];
        }

        $this->state[$context][$scope] =
            $clean;
    }

    /**
     * Clear an entire scope.
     */
    public function clearScope(
        string $context,
        string $scope
    ): void {

        if (
            !$this->isValidName($context) ||
            !$this->isValidName($scope)
        ) {
            return;
        }

        if (
            !isset(
                $this->state[$context]
            ) ||
            !is_array(
                $this->state[$context]
            )
        ) {
            return;
        }

        unset(
            $this->state[$context][$scope]
        );

        if (
            empty(
                $this->state[$context]
            )
        ) {
            unset(
                $this->state[$context]
            );
        }
    }

    /**
     * Replace an entire context.
     *
     * Example:
     *
     *     $state->setContext(
     *         'mycontext',
     *         [
     *             'filter' => [
     *                 'ipn' => '123',
     *             ],
     *             'current' => [
     *                 '_pk' => '42',
     *             ],
     *         ]
     *     );
     */
    public function setContext(
        string $context,
        array $scopes
    ): void {

        if (
            !$this->isValidName($context)
        ) {
            return;
        }

        $clean = [];

        foreach (
            $scopes as $scope => $values
        ) {

            if (
                !is_string($scope) ||
                !$this->isValidName($scope) ||
                !is_array($values)
            ) {
                continue;
            }

            foreach (
                $values as $field => $value
            ) {

                if (
                    !is_string($field) ||
                    !$this->isValidName($field)
                ) {
                    continue;
                }

                if (
                    $value === null ||
                    is_array($value) ||
                    is_object($value)
                ) {
                    continue;
                }

                $value =
                    (string) $value;

                if ($value === '') {
                    continue;
                }

                $clean[$scope][$field] =
                    $value;
            }
        }

        if (empty($clean)) {

            $this->clearContext(
                $context
            );

            return;
        }

        $this->state[$context] =
            $clean;
    }

    /**
     * Clear an entire context.
     */
    public function clearContext(
        string $context
    ): void {

        if (
            !$this->isValidName($context)
        ) {
            return;
        }

        unset(
            $this->state[$context]
        );
    }

    /**
     * Encode the current state for the URL.
     *
     * Uses URL-safe Base64 without "=" padding.
     */
    public function encode(): string
    {
        $json =
            json_encode(
                $this->state,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            );

        return rtrim(
            strtr(
                base64_encode($json),
                '+/',
                '-_'
            ),
            '='
        );
    }

    /**
     * Decode a URL state.
     */
    private function decode(
        string $encoded
    ): void {

        /*
         * Restore standard Base64 alphabet.
         */
        $encoded =
            strtr(
                $encoded,
                '-_',
                '+/'
            );

        /*
         * Restore padding.
         */
        $padding =
            strlen($encoded) % 4;

        if ($padding !== 0) {
            $encoded .=
                str_repeat(
                    '=',
                    4 - $padding
                );
        }

        $json =
            base64_decode(
                $encoded,
                true
            );

        if ($json === false) {
            return;
        }

        try {

            $state =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

        } catch (Throwable $e) {

            return;
        }

        if (!is_array($state)) {
            return;
        }

        /*
         * Only accept the new:
         *
         *     context.scope.field
         *
         * structure.
         *
         * Invalid/non-array context values are ignored.
         */
        $clean = [];

        foreach (
            $state as $context => $scopes
        ) {

            if (
                !is_string($context) ||
                !$this->isValidName($context) ||
                !is_array($scopes)
            ) {
                continue;
            }

            foreach (
                $scopes as $scope => $values
            ) {

                if (
                    !is_string($scope) ||
                    !$this->isValidName($scope) ||
                    !is_array($values)
                ) {
                    continue;
                }

                foreach (
                    $values as $field => $value
                ) {

                    if (
                        !is_string($field) ||
                        !$this->isValidName($field)
                    ) {
                        continue;
                    }

                    if (
                        $value === null ||
                        is_array($value) ||
                        is_object($value)
                    ) {
                        continue;
                    }

                    $clean[$context][$scope][$field] =
                        (string) $value;
                }
            }
        }

        $this->state =
            $clean;
    }

    /**
     * Validate a context, scope or field name.
     *
     * Dots are deliberately forbidden here because they
     * separate:
     *
     *     context.scope.field
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