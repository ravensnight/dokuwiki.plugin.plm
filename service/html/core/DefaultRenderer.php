<?php
/**
 * DefaultRenderer Class
 * 
 * This is the default renderer used when no custom renderer is specified.
 * It handles basic content rendering for HTML elements.
 */
require_once '../../util/HtmlBuilder.php';
require_once 'AbstractRenderer.php';

class DefaultRenderer extends AbstractRenderer
{
    /**
     * Render method for default content handling
     * 
     * @param HtmlBuilder $html The HTML builder instance from util directory
     * @param mixed $content The content to render
     * @param mixed $data The data provided by DataProvider
     * @return void
     */
    public function render(HtmlBuilder $html, $content, $data = null): void
    {
        // Merge content with data if both exist (the normal case for most renders)
        if ($content !== null && $data !== null) {
            $content = $data;
        } elseif ($content === null && $data !== null) {
            $content = $data;
        }
        
        // Handle scalar content directly
        if (is_scalar($content) || is_null($content)) {
            if ($content !== null) {
                $html->add((string)$content);
            }
            return;
        } 
        
        // Handle array content (tag nodes)
        if (is_array($content)) {
            // Check if this is an array of tag nodes  
            if (isset($content[0]) && isset($content[0]['tag'])) {
                // Render each tag node in the array
                foreach ($content as $tagNode) {
                    if (isset($tagNode['tag'])) {
                        $this->renderTagNode($tagNode, $html);
                    }
                }
            } else {
                // For other array content, render as JSON or string representation  
                $html->add(json_encode($content));
            }
        }
    }
    
    /**
     * Helper method to render individual tag nodes using the same HtmlBuilder instance
     * 
     * @param array $tagNode The tag node to render
     * @param HtmlBuilder $htmlBuilder The HTML builder to append to
     * @return void
     */
    private function renderTagNode(array $tagNode, HtmlBuilder $htmlBuilder): void
    {
        // Check if a custom renderer is specified for this tag
        $rendererName = $tagNode['renderer'] ?? null;
        $content = $tagNode['content'] ?? null;
        
        // Create the tag with its attributes
        $attrs = $tagNode['attrs'] ?? [];
        $tag = $tagNode['tag'];
        
        if ($attrs) {
            $htmlBuilder->opn($tag, $attrs['class'] ?? null, $attrs);
        } else {
            $htmlBuilder->opn($tag);
        }
        
        // If a custom renderer is specified, instantiate and use it
        if ($rendererName) {
            // Create instance dynamically - we need to use proper loading
            $rendererInstance = $this->createRendererInstance($rendererName);
            if ($rendererInstance) {
                $rendererInstance->render($htmlBuilder, $content, null); // Data handled at upper level
            } else {
                // Fall back to default behavior
                $this->renderContent($htmlBuilder, $content);
            }
        } else {
            // Use current renderer for content (recursive approach)
            $this->renderContent($htmlBuilder, $content);
        }
        
        // Close the tag
        $htmlBuilder->cls();
    }
    
    /**
     * Helper method to create renderer instances dynamically
     * 
     * @param string $rendererName The name of the renderer class to instantiate
     * @return AbstractRenderer|null The instantiated renderer or null on failure
     */
    private function createRendererInstance(string $rendererName): ?AbstractRenderer
    {
        // Try to instantiate the renderer 
        try {
            // Direct approach first - if already loaded
            if (class_exists($rendererName) && is_subclass_of($rendererName, AbstractRenderer::class)) {
                return new $rendererName();
            }
            
            // Try to load from test directory
            $filePath = "test/{$rendererName}.php";
            if (file_exists($filePath)) {
                require_once $filePath;
                if (class_exists($rendererName) && is_subclass_of($rendererName, AbstractRenderer::class)) {
                    return new $rendererName();
                }
            }
            
            // Try to load from core directory
            $filePath = "core/{$rendererName}.php";
            if (file_exists($filePath)) {
                require_once $filePath;
                if (class_exists($rendererName) && is_subclass_of($rendererName, AbstractRenderer::class)) {
                    return new $rendererName();
                }
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Helper method to render content recursively based on the type
     * 
     * @param HtmlBuilder $htmlBuilder The HTML builder instance
     * @param mixed $content The content to render
     * @return void
     */
    private function renderContent(HtmlBuilder $htmlBuilder, $content): void
    {
        // This handles the recursive rendering of content
        if (is_scalar($content) || is_null($content)) {
            if ($content !== null) {
                $htmlBuilder->add((string)$content);
            }
        } elseif (is_array($content)) {
            // If array contains tag nodes, recursively call renderTagNode
            if (isset($content[0]) && isset($content[0]['tag'])) {
                foreach ($content as $subTagNode) {
                    if (isset($subTagNode['tag'])) {
                        $this->renderTagNode($subTagNode, $htmlBuilder);
                    }
                }
            } else {
                // Direct rendering of regular array data
                $htmlBuilder->add(json_encode($content));
            }
        }
    }
}
?>