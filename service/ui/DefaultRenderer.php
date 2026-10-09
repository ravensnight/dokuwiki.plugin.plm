<?php

/**
 * Default implementation of the Renderer class.
 * Handles standard tag rendering logic.
 */
class DefaultRenderer extends Renderer {
    
    /**
     * Overridable method for specific tag logic if needed.
     * In the default case, it follows the standard structure.
     */
    public function render(Writer $writer, SimpleXMLElement $receipt, array $context): void {
        // The base Renderer class provides the structural logic (start tag, children, end tag).
        // This class can be extended to override specific tag behaviors.
        parent::render($writer, $receipt, $context);
    }
}
