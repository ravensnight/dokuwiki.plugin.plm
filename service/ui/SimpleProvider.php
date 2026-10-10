<?php

/**
 * A default provider that returns the provided context as a single-item list.
 * Used when no specific provider is defined for a tag.
 */
class SimpleProvider extends Provider {
    /**
     * Provides data for rendering.
     * 
     * @param array $context The base context.
     * @return array An array of context items to be rendered.
     */
    public function provide(array $context): array {
        return [$context];
    }
}
