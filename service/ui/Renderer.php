<?php

/**
 * Abstract class for all UI Renderers.
 */
abstract class Renderer {
    /**
     * Renders a given XML tag and its children.
     * 
     * @param Writer $writer The output writer.
     * @param SimpleXMLElement $receipt The parsed XML tag.
     * @param array $context Data context for rendering.
     */
    public function render(Writer $writer, SimpleXMLElement $receipt, array $context): void {
        $tagName = $receipt->getName();

        // Extract raw attributes
        $attributes = [];
        foreach ($receipt->attributes() as $name => $value) {
            // Skip plm: attributes
            if (strpos($name, 'plm:') !== 0) {
                $attributes[$name] = (string)$value;
            }
        }

        // Extract children
        $children = [];
        foreach ($receipt->children() as $child) {
            $children[] = $child;
        }

        // Extract text content
        $textContent = (string)$receipt;

        // Delegate to the new unified renderTag method
        $this->renderTag($writer, $tagName, $attributes, $children, $context, $textContent);
    }

    /**
     * Handles the construction of the tag, attribute substitution, and child recursion.
     */
    protected function renderTag(Writer $writer, string $tagName, array $attributes, array $children, array $context, string $textContent): void {
        // 1. Perform attribute substitution
        $substitutedAttributes = [];
        foreach ($attributes as $name => $value) {
            $substitutedAttributes[$name] = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($matches) use ($context) {
                $key = $matches[1];
                return isset($context[$key]) ? $context[$key] : '';
            }, $value);
        }

        // 2. Construct opening tag
        $attrString = '';
        if (!empty($substitutedAttributes)) {
            $attrParts = [];
            foreach ($substitutedAttributes as $key => $val) {
                $attrParts[] = sprintf(' %s="%s"', $key, $val);
            }
            $attrString = implode('', $attrParts);
        }

        $writer->write("<$tagName" . $attrString . ">");

        $this->renderContent($writer, $textContent, $children, $context);

        // 4. Write closing tag
        $writer->write("</$tagName>");
    }

    /**
     * Hook for concrete classes to override the internal content rendering.
     * By default, it does nothing (handled by the loop in renderTag).
     */
    protected function renderContent(Writer $writer, string $textContent, array $children, array $context): void {

        // Write inner text content if it exists
        if (trim($textContent) !== '') {
            $writer->write($textContent);
        }

        // 3. Render children
        foreach ($children as $child) {
            $rendererClass = 'DefaultRenderer';
            if (isset($child['plm:renderer'])) {
                $rendererClass = $child['plm:renderer'];
            }

            $rendererInstance = RendererRegistry::getRenderer($rendererClass);
            $rendererInstance->render($writer, $child, $context);
        }
    }
}

