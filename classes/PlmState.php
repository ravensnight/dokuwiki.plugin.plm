<?php

class PlmState
{
    /** @var array */
    private $state = [];

    public function __construct(
        ?string $encoded = null
    ) {
        if ($encoded === null) {
            global $INPUT;

            if (
                !isset($INPUT) ||
                $INPUT === null
            ) {
                return;
            }

            $encoded =
                $INPUT->str('plm');
        }

        if (
            $encoded === null ||
            $encoded === ''
        ) {
            return;
        }

        $json =
            $this->base64UrlDecode(
                $encoded
            );

        if ($json === null) {
            return;
        }

        $state =
            json_decode(
                $json,
                true
            );

        if (!is_array($state)) {
            return;
        }

        $this->state =
            $state;
    }

    public function get(
        string $path
    ): string {
        $parts =
            explode(
                '.',
                $path
            );

        $value =
            $this->state;

        foreach ($parts as $part) {

            if (
                !is_array($value) ||
                !array_key_exists(
                    $part,
                    $value
                )
            ) {
                return '';
            }

            $value =
                $value[$part];
        }

        if (
            $value === null ||
            is_array($value)
        ) {
            return '';
        }

        return (string) $value;
    }

    public function getFilter(
        string $name
    ): array {
        if (
            !isset(
                $this->state['filter'][$name]
            ) ||
            !is_array(
                $this->state['filter'][$name]
            )
        ) {
            return [];
        }

        return
            $this->state['filter'][$name];
    }

    public function setFilter(
        string $name,
        string $field,
        string $value
    ): void {
        if (
            !isset(
                $this->state['filter']
            ) ||
            !is_array(
                $this->state['filter']
            )
        ) {
            $this->state['filter'] = [];
        }

        if (
            !isset(
                $this->state['filter'][$name]
            ) ||
            !is_array(
                $this->state['filter'][$name]
            )
        ) {
            $this->state['filter'][$name] = [];
        }

        $this->state['filter'][$name][$field] =
            $value;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function encode(): string
    {
        $json =
            json_encode(
                $this->state,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        if ($json === false) {
            return '';
        }

        return
            $this->base64UrlEncode(
                $json
            );
    }

    private function base64UrlEncode(
        string $value
    ): string {
        return rtrim(
            strtr(
                base64_encode($value),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private function base64UrlDecode(
        string $value
    ): ?string {
        $value =
            strtr(
                $value,
                '-_',
                '+/'
            );

        $padding =
            strlen($value) % 4;

        if ($padding > 0) {
            $value .=
                str_repeat(
                    '=',
                    4 - $padding
                );
        }

        $decoded =
            base64_decode(
                $value,
                true
            );

        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }
}