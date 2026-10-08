<?php
/**
 * AbstractRenderer Class
 * 
 * Base class for all renderers in the Html Bakery Framework.
 */
abstract class AbstractRenderer
{
    /**
     * Render method - must be implemented by subclasses
     * 
     * @param HtmlBuilder $html The HTML builder instance
     * @param mixed $content The content to render
     * @param mixed $data The data provided by DataProvider
     * @return void
     */
    abstract public function render(HtmlBuilder $html, $content, $data = null);
}
?>