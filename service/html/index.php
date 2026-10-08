<?php
/**
 * Html Bakery Framework - Main Entry Point
 * 
 * This file serves as the primary entry point for the Html Bakery Framework.
 * It initializes the framework and demonstrates its capabilities.
 */

// Include the main HtmlBakery class
require_once 'core/HtmlBakery.php';

// Create an instance of the HtmlBakery
$htmlBakery = new HtmlBakery();

// Example usage with a simple template
$template = [
    [
        "tag" => "div",
        "attrs" => ["class" => "container"],
        "content" => "Hello World"
    ]
];

// Render the template
$html = $htmlBakery->render($template);

// Output the result
echo $html;
?>