<?php
/**
 * Complex test file to verify the Html Bakery Framework works correctly
 * with DataProvider and custom renderer functionality
 */
require_once __DIR__ . '/../core/HtmlBakery.php';

echo "<h1>Html Bakery Framework Complex Test</h1>";

// Test: Complex template with DataProvider and custom renderer
echo "<h2>Test: Complex Table with DataProvider and Custom Renderer</h2>";
$bakery = new HtmlBakery();

// Load the complex template
$template = json_decode(file_get_contents(__DIR__ . '/complex_test_template.json'), true);

try {
    $result = $bakery->render($template);
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Status: <span style='color: green;'>SUCCESS</span></p>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Status: <span style='color: red;'>FAILED</span></p>";
}

echo "<h2>Complex Test Complete</h2>";

// Also run the existing tests to ensure backward compatibility
echo "<h2>Running Existing Tests</h2>";

// Test 1: Basic template
echo "<h3>Test 1: Basic Template</h3>";
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
echo "<h3>Test 2: Array of Tag Nodes in Content</h3>";
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
?>