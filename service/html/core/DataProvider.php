<?php
/**
 * DataProvider Abstract Class
 * 
 * Base class for all DataProviders in the Html Bakery Framework.
 * DataProviders are responsible for providing data to templates.
 */
abstract class DataProvider
{
    /**
     * Provide data for rendering
     * 
     * @param mixed $context Context provided by HtmlBakery
     * @return mixed The data to be used for rendering
     */
    abstract public function provide($context);
}
?>