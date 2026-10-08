<?php
/**
 * HtmlBakery Class
 * 
 * This is the main class of the Html Bakery Framework.
 * It orchestrates the parsing, rendering, and processing of templates.
 */
require_once '../../util/HtmlBuilder.php';
require_once 'AbstractRenderer.php';
require_once 'DefaultRenderer.php';
require_once 'DataProvider.php';
require_once 'TemplateParser.php';

class HtmlBakery
{
    private TemplateParser $templateParser;
    private DefaultRenderer $defaultRenderer;
    
    public function __construct()
    {
        $this->templateParser = new TemplateParser();
        $this->defaultRenderer = new DefaultRenderer();
    }
    
    /**
     * Render a template to HTML
     * 
     * @param array $template The JSON template to render
     * @param mixed $context Optional context to pass to DataProviders
     * @return string The rendered HTML
     */
    public function render(array $template, $context = null): string
    {
        // Validate the template
        if (!$this->templateParser->validate($template)) {
            throw new Exception('Invalid template structure');
        }
        
        // Create HTML builder from util
        $htmlBuilder = new HtmlBuilder();
        
        // Process the template
        $this->processTemplate($template, $htmlBuilder, $context);
        
        return $htmlBuilder->getHtml();
    }

    /**
     * Process a template recursively
     * 
     * @param mixed $template The template to process
     * @param HtmlBuilder $htmlBuilder The HTML builder instance
     * @param mixed $context Optional context
     * @return void
     */
    private function processTemplate($template, HtmlBuilder $htmlBuilder, $context = null): void
    {
        // If template is an array, it's an array of tag nodes or scalar content
        if (is_array($template)) {
            // Check if first element is a tag node (array with 'tag' key)
            if (isset($template[0]) && isset($template[0]['tag'])) {
                // Array of tag nodes
                foreach ($template as $tagNode) {
                    $this->processTagNode($tagNode, $htmlBuilder, $context);
                }
            } else {
                // Scalar or regular array content - delegate to default renderer
                $this->defaultRenderer->render($htmlBuilder, $template, $context);
            }
        } else {
            // Scalar template content
            $this->defaultRenderer->render($htmlBuilder, $template, $context);
        }
    }

    /**
     * Process an individual tag node
     * 
     * @param array $tagNode The tag node to process
     * @param HtmlBuilder $htmlBuilder The HTML builder instance
     * @param mixed $context Optional context
     * @return void
     */
    private function processTagNode(array $tagNode, HtmlBuilder $htmlBuilder, $context = null): void
    {
        $tag = $tagNode['tag'];
        $attrs = $tagNode['attrs'] ?? [];
        $content = $tagNode['content'] ?? null;
        $dataProvider = $tagNode['dataProvider'] ?? null;
        $renderer = $tagNode['renderer'] ?? null;
        
        // Handle DataProvider if specified
        if ($dataProvider !== null) {
            // Load the DataProvider if needed
            try {
                if (!class_exists($dataProvider)) {
                    $filePath = "test/{$dataProvider}.php";
                    if (file_exists($filePath)) {
                        require_once $filePath;
                    }
                }
                
                if (class_exists($dataProvider)) {
                    $dataProviderInstance = new $dataProvider();
                    if ($dataProviderInstance instanceof DataProvider) {
                        $data = $dataProviderInstance->provide($context);
                        // If content is null, use the data from DataProvider
                        if ($content === null && $data !== null) {
                            $content = $data;
                        } elseif ($content !== null) {
                            // Merge content and data appropriately
                            // For now, use data as content
                            $content = $data;
                        }
                    }
                }
            } catch (Exception $e) {
                // Handle error gracefully
                // Keep existing content if DataProvider fails
            }
        }

        // Create the tag with its attributes
        if ($attrs) {
            $htmlBuilder->opn($tag, $attrs['class'] ?? null, $attrs);
        } else {
            $htmlBuilder->opn($tag);
        }
        
        // If a custom renderer is specified, use it instead of the default
        if ($renderer !== null) {
            // Determine the renderer to use
            $rendererInstance = $this->createRendererInstance($renderer);
            if ($rendererInstance) {
                $rendererInstance->render($htmlBuilder, $content, $context);
            } else {
                // Fall back to default renderer
                $this->defaultRenderer->render($htmlBuilder, $content, $context);
            }
        } else {
            // Use default renderer for content handling
            $this->defaultRenderer->render($htmlBuilder, $content, $context);
        }
        
        // Close the tag
        $htmlBuilder->cls();
    }
    
    /**
     * Helper method to create renderer instances dynamically
     * 
     * @param string $rendererName The name of the renderer class
     * @return AbstractRenderer|null The instantiated renderer or null on failure
     */
    private function createRendererInstance(string $rendererName): ?AbstractRenderer
    {
        try {
            // Direct approach first
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
}
?>