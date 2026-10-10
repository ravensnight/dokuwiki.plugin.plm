<?php

/**
 * Abstract class for all UI Renderers.
 */
abstract class Renderer {
    /**
     * Renders a given XML tag and its children.
     * 
     * @param Writer $writer The output writer.
     * @param SimpleXMLElement $receipt - The parsed XML tag.
     * @param array $context - Data context for rendering.
     */
    abstract public function render(Writer $writer, SimpleXMLElement $receipt, array $context): void;

    /**
     * Identifies the provider and fetches the data based on the tag attributes.
     * @param array $parentContext The base context.
     * @return array The provider data (each element is a merged context).
     */
    protected function buildChildContextArray(array $resolvedAttrs, array $parentContext): array {

        $extendedContext = $parentContext;
        // check additional context attributes
        if (isset($resolvedAttrs['plm:context'])) {
            $json = $resolvedAttrs['plm:context'];

            if ($json) {
                $addContext = json_decode($json, true);
                if (is_array($addContext)) {
                    $extendedContext = array_merge($extendedContext, $addContext);
                }
            }
        }

        $providerClass = $resolvedAttrs['plm:provider'] ?? 'SimpleProvider';

        $provider = ProviderRegistry::getProvider($providerClass);
        $rawProviderData = $provider->provide($extendedContext);

        // Ensure we are working with a list of items
        $items = is_array($rawProviderData) ? $rawProviderData : [$rawProviderData];

        $mergedResults = [];
        foreach ($items as $item) {
            // If the provider item is an array, merge it with parent context.
            // array_merge($parent, $child) ensures child overrides parent.
            if (is_array($item)) {
                $mergedResults[] = array_merge($extendedContext, $item);
            } else {
                // If the provider returns a non-array (e.g. a stdClass),
                // we convert to array and merge.
                $mergedResults[] = array_merge($extendedContext, (array)$item);
            }
        }

        return $mergedResults;
    }

    /**
     * Substitue attributes from values in a given context.
     */
    protected static function resolveAttributes(array $attributes, array $context): array
    {
        // 2. Perform attribute substitution
        $substitutedAttributes = [];
        foreach ($attributes as $name => $value) {
            $newval = SubstitutionUtility::resolveBraces($value, $context);
            if (!empty($newval)) {
                $substitutedAttributes[$name] = $newval;
            }
        }

        return $substitutedAttributes;
    }

    /**
     * Substitue attributes from values in a given context.
     */
    protected static function buildAttributeString(array $attributes) : string {

        // create the attribute string
        $attrString = '';
        if (!empty($attributes)) {
            $attrParts = [];
            foreach ($attributes as $key => $val) {
                // Skip internal plm attributes from appearing in the final tag attributes
                if (strpos($key, 'plm:') === 0) {
                    continue;
                }

                $attrParts[] = sprintf(' %s="%s"', $key, $val);
            }
            $attrString = implode('', $attrParts);
        }

        return $attrString;
    }
}

