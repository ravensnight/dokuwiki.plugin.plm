<?php

/**
 * Default implementation of the Renderer class.
 * Handles standard tag rendering logic.
 */
class DefaultRenderer extends Renderer {
    
    /**
     * Renders a given XML tag and its children.
     * 
     * @param Writer $writer The output writer.
     * @param SimpleXMLElement $receipt - The parsed XML tag.
     * @param array $context - Data context for rendering.
     */
    public function render(Writer $writer, SimpleXMLElement $receipt, array $context): void {
        $tagName = $receipt->getName();

        // Extract raw attributes
        $attributes = [];
        foreach ($receipt->attributes() as $name => $value) {
            $attributes[$name] = (string)$value;
        }

        // Extract children
        $children = [];
        foreach ($receipt->children() as $child) {
            $children[] = $child;
        }

        // Extract text content
        $textContent = (string)$receipt;
        $this->renderTag($writer, $tagName, $attributes, $children, $context, $textContent);
    }

    /**
     * Handles the construction of the tag, attribute substitution, and child recursion.
     */
    protected function renderTag(Writer $writer, string $tagName, array $attributes, array $children, array $parentContext, string $textContent): void
    {
        // 2. Perform attribute substitution
        $resolvedAttrs = Renderer::resolveAttributes($attributes, $parentContext);

        $condition = true;
        if (isset($resolvedAttrs['plm:if'])) {
            $val = $resolvedAttrs['plm:if'];
            if (!isset($parentContext[$val])) {
                $condition = false;
            }
            else {
                $val = $parentContext[$val];
                if (!$val) {
                    $condition = false;
                }
            }
        }

        // do not render, if condition is not fulfilled
        if (!$condition) {
            return;
        }

        // get origin attributes as tags
        $attrString = Renderer::buildAttributeString($resolvedAttrs);

        /** @var array $providerData */
        $childContextArray = Renderer::buildChildContextArray($resolvedAttrs, $parentContext);

        // Write opening tag ONCE
        $writer->write("<$tagName" . $attrString . ">");

        // render the content
        foreach ($childContextArray as $innerContext) {
            // Call the shared rendering logic
            // echo "Tag: " . $tagName . " | Context: " . json_encode($innerContext) . "<br>";
            $this->renderContent($writer, $textContent, $children, $innerContext);
        }

        // 5. Write closing tag ONCE
        $writer->write("</$tagName>");
    }

    /**
     * Hook for concrete classes to override the internal content rendering.
     * By default, it does nothing (handled by the loop in renderTag).
     */
    protected function renderContent(Writer $writer, string $textContent, array $children, array $childContext): void
    {
        $substitutedText = SubstitutionUtility::resolveBraces($textContent, $childContext);

        if (trim($substitutedText) !== '') {
            $writer->write($substitutedText);
        }

        foreach ($children as $child) {
            $rendererClass = 'DefaultRenderer';
            if (isset($child['plm:renderer'])) {
                $rendererClass = $child['plm:renderer'];
            }

            $rendererInstance = RendererRegistry::getRenderer($rendererClass);
            $rendererInstance->render($writer, $child, $childContext);
        }
    }    
}
