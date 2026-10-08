<?php
/**
 * Simple test to verify the Html Bakery Framework works correctly
 * This test directly uses the current working directory structure
 */

// Use absolute path to core directory
require_once __DIR__ . '/../core/HtmlBakery.php';

echo "<h1>Html Bakery Framework Test</h1>";

// Test simple example based on the original test case
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
    echo "<h2>Basic Template Test</h2>";
    echo "<p>Result: " . htmlspecialchars($result1) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
    
    // Test array of tag nodes content
    echo "<h2>Array of Tag Nodes Test</h2>";
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
    
    $result2 = $bakery->render($template2);
    echo "<p>Result: " . htmlspecialchars($result2) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Status: <span style='color: red;'>FAILED</span></p>";
}

echo "<h2>Framework Test Complete</h2>";
?>