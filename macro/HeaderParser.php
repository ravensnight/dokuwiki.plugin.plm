<?php

class HeaderParser {

    public static function parse(string $header) : ?MacroHeader {

        $header = trim($header);

        // /plm: ...
        if (!preg_match(
            '/^'
            . '([a-zA-Z0-9_-]+)'                    // macro ... the macro, using
            . '\s*>\s*'                             // >    
            . '([a-zA-Z0-9_-]+)'                    // context ... the context, this is relevant for state data 
            . '(.*)$'                               // details ... the next optional part to parse
            . '/',
            $header,
            $match
        )) {
            return null;
        }

        $macro = strtolower($match[1]);
        $context = trim($match[2]);
        $details = trim($match[3]);
        $reference = null;
        $filter = null;
        $message = null;

        if (preg_match(
            '/^'
                . '\s*\|\s*'                            // | ... details separator
                . '([a-zA-Z0-9_-]+)'                    // reference ... a reference, this macro releates to
                . '(?:\s*\[([^\]]*)\])?'                // [filter] ... some filter inside reference
                . '(?:\s+"((?:\\\\.|[^"\\\\])*)")?'     // " some text " ... an info or error text
                . '\s*$'
                . '/',
            $details,
            $match

        )) {
            $reference = strtolower(trim($match[1]));
            $filter = isset($match[2]) ? trim($match[2]) : null;
            $error = isset($match[3]) ? stripcslashes($match[3]) : null;
        }

        return new MacroHeader($macro, $context, $reference, $filter, $message);
    }

}
