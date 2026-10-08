<?php
/**
 * Test file to verify the Html Bakery Framework works correctly
 */
require_once '../core/HtmlBakery.php';

echo "<h1>Html Bakery Framework Test</h1>";

// Test 1: Basic template
echo "<h2>Test 1: Basic Template</h2>";
$bakery = new HtmlBakery();
$template1 = [
    [
        "tag" => "div",
        "attrs" => ["class" => "container"],
        "content" => "Hello World"
    ]
];

try {
    $result1 = $bakery->render($template1);
    echo "<p>Result: " . htmlspecialchars($result1) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Status: <span style='color: red;'>FAILED</span></p>";
}

// Test 2: Array of tag nodes in content
echo "<h2>Test 2: Array of Tag Nodes in Content</h2>";
$template2 = [
    [
        "tag" => "div",
        "attrs" => ["class" => "container"],
        "content" => [
            [
                "tag" => "h1",
                "attrs" => ["class" => "title"],
                "content" => "Hello World"
            ],
            [
                "tag" => "p",
                "attrs" => ["class" => "description"],
                "content" => "This is a paragraph"
            ]
        ]
    ]
];

try {
    $result2 = $bakery->render($template2);
    echo "<p>Result: " . htmlspecialchars($result2) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Status: <span style='color: red;'>FAILED</span></p>";
}

echo "<h2>Framework Test Complete</h2>";
?>