<?php

/**
 * Utility class for handling variable substitution within strings.
 */
class SubstitutionUtility {
    /**
     * Replaces placeholders in a string based on a provided context array.
     * 
     * Supports the {key} format.
     * 
     * @param string $input The source string containing placeholders.
     * @param array $context The key-value pairs for substitution.
     * @return string The substituted string.
     */
    public static function resolveBraces(string $input, array $context): string {
        return preg_replace_callback('/\{([a-zA-Z0-9_:-]+)\}/', function($matches) use ($context) {
            $key = $matches[1];
            return $context[$key] ?? '';
        }, $input);
    }

    /**
     * Replaces placeholders in a string based on a provided context array.
     * 
     * Supports the @<key> format (planned for future use).
     * 
     * @param string $input The source string containing placeholders.
     * @param array $context The key-value pairs for substitution.
     * @return string The substituted string.
     */
    public static function resolveAtTags(string $input, array $context): string {
        // Placeholder for future implementation
        return $input;
    }
}
