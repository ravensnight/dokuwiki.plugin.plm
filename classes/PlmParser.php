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
             * template is parsed separately below.
             */
            if (
                $name === 'field' ||
                $name === 'action'
            ) {
                $params[$name][] =
                    $this->tokenize($value);

                continue;
            }

            /*
             * Templates are kept as complete token arrays
             * until they can be normalized below.
             */
            if ($name === 'template') {
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
                $name === 'action' ||
                $name === 'template'
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
         * ---------------------------------------------------------
         * TEMPLATES
         * ---------------------------------------------------------
         *
         * Example:
         *
         * template: link_edit "Mein Link" [[ :intern:plm:partedit?_pk=$_pk | Open Part ]]
         *
         * Result:
         *
         * $params['templates']['link_edit'] = [
         *     'label'   => 'Mein Link',
         *     'content' => '[[ :intern:plm:partedit?_pk=$_pk | Open Part ]]',
         * ];
         *
         * The old $params['template'] representation is retained
         * for compatibility with existing PlmTable code.
         */
        if (isset($params['template'])) {

            $templateDefinitions = [];
            $legacyTemplates = [];

            foreach ($params['template'] as $tokens) {

                if (empty($tokens)) {
                    continue;
                }

                $templateName =
                    trim(
                        (string) ($tokens[0] ?? '')
                    );

                if ($templateName === '') {
                    throw new InvalidArgumentException(
                        'PLM template requires a name.'
                    );
                }

                if (!preg_match(
                    '/^[a-zA-Z_][a-zA-Z0-9_-]*$/',
                    $templateName
                )) {
                    throw new InvalidArgumentException(
                        'Invalid PLM template name: ' .
                        $templateName
                    );
                }

                /*
                 * Optional display label.
                 *
                 * Example:
                 *
                 * "Mein Link"
                 */
                $label = '';

                if (
                    isset($tokens[1]) &&
                    is_string($tokens[1])
                ) {
                    $label =
                        (string) $tokens[1];
                }

                /*
                 * Everything after the name and optional
                 * label belongs to the template content.
                 */
                $contentTokens =
                    array_slice(
                        $tokens,
                        2
                    );

                /*
                 * Reconstruct the template content with
                 * spaces between tokens.
                 */
                $templateContent =
                    implode(
                        ' ',
                        $contentTokens
                    );

                /*
                 * Store the structured representation.
                 */
                $templateDefinitions[$templateName] = [
                    'label' =>
                        $label,

                    'content' =>
                        $templateContent,
                ];

                /*
                 * Keep the previous representation:
                 *
                 * [
                 *     name,
                 *     label,
                 *     content...
                 * ]
                 */
                $legacyTemplates[$templateName] = [
                    $templateName,
                    $label,
                    ...$contentTokens,
                ];
            }

            $params['templates'] =
                $templateDefinitions;

            /*
             * For compatibility, retain the old singular
             * template representation for the first template.
             */
            if (!empty($legacyTemplates)) {

                $firstTemplate =
                    reset(
                        $legacyTemplates
                    );

                $params['template'] =
                    $firstTemplate;
            } else {

                $params['template'] = [];
            }
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

        /*
         * delete has exactly one field.
         *
         * Example:
         *
         * delete: ipn
         */
        if (isset($params['delete'])) {

            if (count($params['delete']) !== 1) {
                throw new InvalidArgumentException(
                    'PLM parameter "delete" requires exactly one field.'
                );
            }

            $deleteField =
                trim(
                    $params['delete'][0]
                );

            if (!preg_match(
                '/^[a-zA-Z0-9_.-]+$/',
                $deleteField
            )) {
                throw new InvalidArgumentException(
                    'Invalid PLM delete field: ' .
                    $deleteField
                );
            }

            $params['delete'] =
                $deleteField;
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

            $parts =
                explode(
                    ',',
                    $token
                );

            foreach ($parts as $part) {

                $part =
                    trim(
                        $part
                    );

                if ($part !== '') {
                    $result[] =
                        $part;
                }
            }
        }

        return $result;
    }
}