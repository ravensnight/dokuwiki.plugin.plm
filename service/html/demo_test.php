<?php
/**
 * Demonstration of the array handling functionality
 */
require_once 'core/HtmlBakery.php';

echo "<h1>Html Bakery Framework - Array Handling Demo</h1>";

$bakery = new HtmlBakery();

// Test 1: Simple content (scalar)
echo "<h2>Test 1: Simple Scalar Content</h2>";
$template1 = [
    "tag" => "div",
    "attrs" => ["class" => "container"],
    "content" => "Hello World"
];
$result1 = $bakery->render($template1);
echo "<p>Result: " . htmlspecialchars($result1) . "</p>";

// Test 2: Array of tag nodes in content (the requested feature) 
echo "<h2>Test 2: Array of Tag Nodes in Content</h2>";
$template2 = [
    "tag" => "div",
    "attrs" => ["class" => "container"],
    "content" => [
        [
            "tag" => "h1",
            "attrs" => ["class" => "title"],
            "content" => "Header"
        ],
        [
            "tag" => "p", 
            "attrs" => ["class" => "description"],
            "content" => "Paragraph content"
        ]
    ]
];
$result2 = $bakery->render($template2);
echo "<p>Result: " . htmlspecialchars($result2) . "</p>";

// Test 3: Single tag node object in content
echo "<h2>Test 3: Single Tag Node in Content</h2>";
$template3 = [
    "tag" => "div",
    "attrs" => ["class" => "container"],
    "content" => [
        "tag" => "span",
        "attrs" => ["class" => "highlight"],
        "content" => "Highlight text"
    ]
];
$result3 = $bakery->render($template3);
echo "<p>Result: " . htmlspecialchars($result3) . "</p>";

echo "<h2>Demo Complete</h2>";
?>