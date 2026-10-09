<?php

/**
 * Parser for XML-based UI templates.
 */
class HtmlBakery {
    /**
     * Parses an XML template and renders it to the provided writer.
     * 
     * @param Writer $writer The output writer instance.
     * @param string $receipt The path to the XML template file.
     * @param array $context The context array for value injection.
     * @throws Exception if file cannot be loaded or parsed.
     */
    public function parse(Writer $writer, string $receipt, array $context): void {
        if (!file_exists($receipt)) {
            throw new Exception("Template file not found: $receipt");
        }

        // Load XML
        $xml = simplexml_load_file($receipt);
        if ($xml === false) {
            throw new Exception("Failed to parse XML template: $receipt");
        }

        // Start recursive rendering
        $this->renderNode($writer, $xml, $context);
    }

    /**
     * Recursive method to process each XML node.
     */
    private function renderNode(Writer $writer, SimpleXMLElement $node, array $context): void {
        // 2. Determine Renderer
        $rendererClass = 'DefaultRenderer';
        if (isset($node['plm:renderer'])) {
            $rendererClass = $node['plm:renderer'];
        }

        try {
            $renderer = RendererRegistry::getRenderer($rendererClass);
            $renderer->render($writer, $node, $context);
        } catch (Exception $e) {
            // Fallback to DefaultRenderer if specific one fails or is missing
            $default = RendererRegistry::getRenderer('DefaultRenderer');
            $default->render($writer, $node, $context);
        }
    }
}

