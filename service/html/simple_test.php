<?php
/**
 * Simple test to verify basic framework functionality
 */
require_once 'core/HtmlBakery.php';

echo "<h1>Simple Html Bakery Test</h1>";

// Test 1: Simple single tag
echo "<h2>Test 1: Simple Single Tag</h2>";
$bakery = new HtmlBakery();
$template1 = [
    "tag" => "div",
    "attrs" => ["class" => "container"],
    "content" => "Hello World"
];

try {
    $result1 = $bakery->render($template1);
    echo "<p>Result: " . htmlspecialchars($result1) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Status: <span style='color: red;'>FAILED</span></p>";
}

// Test 2: Array of tags
echo "<h2>Test 2: Array of Tags</h2>";
$template2 = [
    [
        "tag" => "h1",
        "attrs" => ["class" => "title"],
        "content" => "Welcome"
    ],
    [
        "tag" => "p",
        "attrs" => ["class" => "description"],
        "content" => "This is a test"
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


echo "<h2>Basic Tests Complete</h2>";
?>