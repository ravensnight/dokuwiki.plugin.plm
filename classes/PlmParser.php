<?php

class PlmParser
{
    /**
     * Parse the content of a PLM block.
     */
    public function parse(string $content): array
    {
        $params = [];

        foreach (preg_split('/\R/', $content) as $line) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (!preg_match(
                '/^([a-zA-Z_][a-zA-Z0-9_-]*)\s*:\s*(.*)$/',
                $line,
                $match
            )) {
                throw new InvalidArgumentException(
                    'Invalid PLM parameter: ' . $line
                );
            }

            $name =
                strtolower(
                    $match[1]
                );

            $value =
                $match[2];

            /*
             * field and action remain line based.
             *
             * Example:
             *
             * field: ipn
             * field: description readonly
             *
             * action: update "Speichern" :parts:part?ipn=$ipn
             */
            if (
                $name === 'field' ||
                $name === 'action'
            ) {

                $params[$name][] =
                    $this->tokenize(
                        $value
                    );

                continue;
            }

            /*
             * All other parameters are normal
             * comma-separated lists.
             *
             * Examples:
             *
             * cols: ipn, description, @link_edit
             * create: ipn, description
             * filter: ipn
             */
            $params[$name][] =
                $this->tokenizeList(
                    $value
                );
        }

        /*
         * Flatten normal list parameters.
         */
        foreach ($params as $name => $values) {

            if (
                $name === 'field' ||
                $name === 'action'
            ) {
                continue;
            }

            $flattened = [];

            foreach ($values as $items) {

                foreach ($items as $item) {

                    $item =
                        trim(
                            $item
                        );

                    if ($item !== '') {
                        $flattened[] =
                            $item;
                    }
                }
            }

            $params[$name] =
                $flattened;
        }

        return $params;
    }

    /**
     * Tokenize a comma-separated parameter list.
     *
     * Quoted values may contain commas.
     *
     * Example:
     *
     *     cols: ipn, description, @link
     */
    private function tokenizeList(
        string $value
    ): array {

        $tokens = [];
        $buffer = '';

        $length =
            strlen(
                $value
            );

        $pos = 0;
        $inQuotes = false;

        while ($pos < $length) {

            $char =
                $value[$pos];

            /*
             * Handle quoted strings.
             */
            if ($char === '"') {

                $inQuotes =
                    !$inQuotes;

                $buffer .=
                    $char;

                $pos++;
                continue;
            }

            /*
             * Comma outside quotes separates
             * list entries.
             */
            if (
                $char === ',' &&
                !$inQuotes
            ) {

                $token =
                    trim(
                        $buffer
                    );

                if ($token !== '') {

                    /*
                     * Remove surrounding quotes and
                     * process escaped characters.
                     */
                    $parsed =
                        $this->tokenize(
                            $token
                        );

                    foreach ($parsed as $part) {
                        $tokens[] =
                            $part;
                    }
                }

                $buffer = '';
                $pos++;

                continue;
            }

            $buffer .=
                $char;

            $pos++;
        }

        /*
         * Last token.
         */
        $token =
            trim(
                $buffer
            );

        if ($token !== '') {

            $parsed =
                $this->tokenize(
                    $token
                );

            foreach ($parsed as $part) {
                $tokens[] =
                    $part;
            }
        }

        return $tokens;
    }

    /**
     * Tokenize a single parameter value.
     */
    private function tokenize(
        string $value
    ): array {

        $tokens = [];

        $length =
            strlen(
                $value
            );

        $pos = 0;

        while ($pos < $length) {

            /*
             * Skip whitespace.
             */
            while (
                $pos < $length &&
                ctype_space(
                    $value[$pos]
                )
            ) {
                $pos++;
            }

            if ($pos >= $length) {
                break;
            }

            /*
             * Quoted token.
             */
            if ($value[$pos] === '"') {

                $pos++;

                $buffer = '';
                $closed = false;

                while ($pos < $length) {

                    if ($value[$pos] === '\\') {

                        if (
                            $pos + 1 >=
                            $length
                        ) {

                            $buffer .=
                                '\\';

                            $pos++;
                            continue;
                        }

                        $next =
                            $value[
                                $pos + 1
                            ];

                        switch ($next) {

                            case '"':

                                $buffer .=
                                    '"';

                                $pos += 2;

                                break;

                            case '\\':

                                $buffer .=
                                    '\\';

                                $pos += 2;

                                break;

                            default:

                                $buffer .=
                                    '\\' .
                                    $next;

                                $pos += 2;

                                break;
                        }

                        continue;
                    }

                    if ($value[$pos] === '"') {

                        $pos++;

                        $closed = true;

                        break;
                    }

                    $buffer .=
                        $value[$pos];

                    $pos++;
                }

                if (!$closed) {

                    throw new InvalidArgumentException(
                        'Unterminated quoted PLM parameter value'
                    );
                }

                $tokens[] =
                    $buffer;

                continue;
            }

            /*
             * Normal token.
             */
            $start =
                $pos;

            while (
                $pos < $length &&
                !ctype_space(
                    $value[$pos]
                )
            ) {
                $pos++;
            }

            $tokens[] =
                substr(
                    $value,
                    $start,
                    $pos - $start
                );
        }

        return $tokens;
    }
}