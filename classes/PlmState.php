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
        global $INPUT;

        /*
        * DokuWiki environment.
        */
        if ($INPUT !== null) {

            $encoded =
                $INPUT->str(
                    self::PARAMETER
                );

        } else {

            /*
            * Standalone / CLI test.
            */
            $encoded =
                $_GET[self::PARAMETER]
                ?? '';
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
            $encoded .= str_repeat(
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