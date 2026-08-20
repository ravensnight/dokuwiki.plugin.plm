<?php

class PlmState
{
    /**
     * URL parameter containing the complete PLM state.
     */
    private const PARAMETER = 'plm';

    /**
     * Internal state.
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
         * Normal DokuWiki request.
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
     * Get all filters for a component.
     *
     * Example:
     *
     *     $state->getFilter('tablecompanies')
     *
     * returns:
     *
     *     [
     *         'name' => 'Anycubic',
     *     ]
     */
    public function getFilter(
        string $name
    ): array {
        return
            $this->state['filter'][$name]
            ?? [];
    }

    /**
     * Get one filter value.
     */
    public function getFilterValue(
        string $name,
        string $field
    ): string {

        $value =
            $this->state['filter'][$name][$field]
            ?? '';

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Set one filter value.
     *
     * Empty values are removed.
     */
    public function setFilterValue(
        string $name,
        string $field,
        string $value
    ): void {

        if ($value === '') {

            $this->clearFilterValue(
                $name,
                $field
            );

            return;
        }

        if (!isset(
            $this->state['filter']
        )) {
            $this->state['filter'] = [];
        }

        if (!isset(
            $this->state['filter'][$name]
        )) {
            $this->state['filter'][$name] = [];
        }

        $this->state['filter'][$name][$field] =
            $value;
    }

    /**
     * Clear one filter value.
     */
    public function clearFilterValue(
        string $name,
        string $field
    ): void {

        if (
            !isset(
                $this->state['filter'][$name][$field]
            )
        ) {
            return;
        }

        unset(
            $this->state['filter'][$name][$field]
        );

        /*
         * Remove empty component.
         */
        if (
            empty(
                $this->state['filter'][$name]
            )
        ) {
            unset(
                $this->state['filter'][$name]
            );
        }

        /*
         * Remove empty filter section.
         */
        if (
            empty(
                $this->state['filter']
            )
        ) {
            unset(
                $this->state['filter']
            );
        }
    }

    /**
     * Get a normal URL parameter.
     *
     * This is used by PLM filter references such as:
     *
     *     _pk=&_pk
     *
     * The value is read from the current request and is
     * deliberately kept separate from the encoded PLM state.
     */
    public function getRequestValue(
        string $name
    ): string {

        if (
            $name === '' ||
            !preg_match(
                '/^[a-zA-Z0-9_-]+$/',
                $name
            )
        ) {
            return '';
        }

        global $INPUT;

        /*
         * Normal DokuWiki request.
         */
        if (
            isset($INPUT) &&
            is_object($INPUT) &&
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
                /*
                 * Fall through to $_GET.
                 */
            }
        }

        /*
         * PHP fallback.
         */
        if (
            isset($_GET[$name]) &&
            !is_array($_GET[$name]) &&
            !is_object($_GET[$name])
        ) {
            return (string) $_GET[$name];
        }

        return '';
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

        $this->state =
            $state;
    }
}