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

            $name = strtolower($match[1]);
            $value = $match[2];

            /*
             * These parameters are line based.
             *
             * Example:
             *
             * field: ipn
             * field: description readonly
             *
             * action: update "Speichern" :parts:part?ipn=$ipn
             * action: create "Create" :parts:part?ipn=$ipn
             *
             * Each line therefore remains its own token array.
             */
            if (
                $name === 'field' ||
                $name === 'action'
            ) {
                $params[$name][] =
                    $this->tokenize($value);

                continue;
            }

            $params[$name][] =
                $this->tokenize($value);
        }

        /*
         * Flatten normal parameters.
         */
        foreach ($params as $name => $values) {

            if (
                $name === 'field' ||
                $name === 'action'
            ) {
                continue;
            }

            $flattened = [];

            foreach ($values as $tokens) {
                foreach ($tokens as $token) {
                    $flattened[] = $token;
                }
            }

            $params[$name] = $flattened;
        }

        /*
         * cols has its own syntax:
         *
         * cols: $ipn, $description, @link
         */
        if (isset($params['cols'])) {
            $params['cols'] =
                $this->parseColumns(
                    $params['cols']
                );
        }

        return $params;
    }

    /**
     * Tokenize a single parameter value.
     */
    private function tokenize(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        $pos = 0;

        while ($pos < $length) {

            while (
                $pos < $length &&
                ctype_space($value[$pos])
            ) {
                $pos++;
            }

            if ($pos >= $length) {
                break;
            }

            if ($value[$pos] === '"') {
                $pos++;

                $buffer = '';
                $closed = false;

                while ($pos < $length) {

                    if ($value[$pos] === '\\') {

                        if ($pos + 1 >= $length) {
                            $buffer .= '\\';
                            $pos++;
                            continue;
                        }

                        $next = $value[$pos + 1];

                        switch ($next) {

                            case '"':
                                $buffer .= '"';
                                $pos += 2;
                                break;

                            case '\\':
                                $buffer .= '\\';
                                $pos += 2;
                                break;

                            default:
                                $buffer .= '\\' . $next;
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

                    $buffer .= $value[$pos];
                    $pos++;
                }

                if (!$closed) {
                    throw new InvalidArgumentException(
                        'Unterminated quoted PLM parameter value'
                    );
                }

                $tokens[] = $buffer;
                continue;
            }

            $start = $pos;

            while (
                $pos < $length &&
                !ctype_space($value[$pos])
            ) {
                $pos++;
            }

            $tokens[] = substr(
                $value,
                $start,
                $pos - $start
            );
        }

        return $tokens;
    }

    /**
     * Parse column definitions.
     */
    private function parseColumns(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $parts = explode(',', $token);

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }

        return $result;
    }
}