<?php
/**
 * TestDataProvider Class
 * 
 * A test DataProvider implementation for demonstrating functionality.
 */
require_once '../core/DataProvider.php';

class TestDataProvider extends DataProvider
{
    /**
     * Provide test data for rendering
     * 
     * @param mixed $context Context provided by HtmlBakery
     * @return array The test data
     */
    public function provide($context)
    {
        return [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25]
        ];
    }
}
?>