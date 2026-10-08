<?php
/**
 * Final test demonstrating the array of tag nodes in content functionality
 */
require_once 'core/HtmlBakery.php';

echo "<h1>Html Bakery Framework - Array Content Test</h1>";

// This will demonstrate that our DefaultRenderer handles array content appropriately
$bakery = new HtmlBakery();

// Test with an object/array content (this requires our updated DefaultRenderer)
echo "<h2>Test: Nested Content with Array of Tag Nodes</h2>";

$template = [
    "tag" => "div",
    "attrs" => ["class" => "container"],
    "content" => [
        "tag" => "p",
        "attrs" => ["class" => "intro"],
        "content" => "This is a paragraph"
    ]
];

try {
    // First, test if a simple case works
    $result = $bakery->render($template); 
    echo "<p>Simple nested tag result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
    
    // Try a more complex nested case
    echo "<h2>Test: Array of Tag Nodes in Content</h2>";
    $complexTemplate = [
        [
            "tag" => "div",
            "attrs" => ["class" => "outer-wrapper"],
            "content" => [
                [
                    "tag" => "h2",
                    "attrs" => ["class" => "title"],
                    "content" => "Header"
                ],
                [
                    "tag" => "p",
                    "attrs" => ["class" => "body"],
                    "content" => "Body content"
                ]
            ]
        ]
    ];
    
    $complexResult = $bakery->render($complexTemplate);
    echo "<p>Complex nested array result: " . htmlspecialchars($complexResult) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Status: <span style='color: red;'>FAILED</span></p>";
}

echo "<h2>Framework Testing Complete</h2>";
?>