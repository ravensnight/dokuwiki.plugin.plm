<?php
/**
 * Final demonstration of PHP ReflectionClass usage
 */

echo "=== PHP REFLECTIONCLASS DEMONSTRATION ===\n\n";

// First, let's make sure all the classes we need are properly included

// Load core classes first
require_once 'core/DataProvider.php';
require_once 'core/AbstractRenderer.php';
require_once 'core/DefaultRenderer.php';

// Load test classes  
require_once 'test/TestDataProvider.php';
require_once 'test/TestCustomRenderer.php';

echo "Classes loaded successfully\n\n";

// Demonstrate ReflectionClass usage
echo "=== DEMONSTRATION 1: Basic ReflectionClass Usage ===\n";

try {
    // Create a class instance using ReflectionClass
    $providerName = 'TestDataProvider';
    $reflection = new ReflectionClass($providerName);
    
    if ($reflection->isInstantiable()) {
        $providerInstance = $reflection->newInstance();
        echo "SUCCESS: Created instance of $providerName\n";
        var_dump($providerInstance);
    } else {
        echo "ERROR: $providerName is not instantiable\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n=== DEMONSTRATION 2: Dynamic Instantiation Function ===\n";

function instantiateClass($className, $args = []) {
    try {
        // Check if class exists first
        if (!class_exists($className)) {
            echo "Class $className does not exist yet.\n";
            return null;
        }
        
        $reflection = new ReflectionClass($className);
        
        if ($reflection->isInstantiable()) {
            $instance = $reflection->newInstance(...$args);
            echo "SUCCESS: Created instance of $className\n";
            return $instance;
        } else {
            echo "ERROR: $className is not instantiable\n";
            return null;
        }
    } catch (Exception $e) {
        echo "EXCEPTION creating $className: " . $e->getMessage() . "\n";
        return null;
    }
}

// Test both providers
echo "Creating TestDataProvider instance:\n";
$testProvider = instantiateClass('TestDataProvider');

echo "\nCreating TestCustomRenderer instance:\n";
$testRenderer = instantiateClass('TestCustomRenderer');

echo "\n=== DEMONSTRATION 3: Using Providers in Your Context ===\n";

// Simulate what you might do in HtmlBakery with DataProvider
if ($testProvider !== null) {
    // Test the provide method
    $context = ['some' => 'context'];
    $data = $testProvider->provide($context);
    echo "DataProvider provided: " . print_r($data, true) . "\n";
}

echo "\n=== SAMPLE FUNCTION FOR YOUR HTMLBAKERY ===\n";

/**
 * Utility function to instantiate classes that might be used in HtmlBakery
 * 
 * @param string $className The name of the class to instantiate
 * @param array $constructorArgs Arguments to pass to constructor if needed
 * @return object|null The instantiated object or null on failure
 */
function createClassInstance($className, $constructorArgs = []) {
    try {
        // Before instantiating, ensure the class can be loaded
        // This assumes you've already included the necessary files
        if (!class_exists($className)) {
            // Try to load the file - this would need to be adapted to your structure
            $filesToTry = [
                "core/{$className}.php",
                "test/{$className}.php",
                "lib/{$className}.php",  // Add other paths as needed
            ];
            
            foreach ($filesToTry as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    echo "Loaded file: $file\n";
                    break;
                }
            }
        }
        
        if (!class_exists($className)) {
            echo "ERROR: Could not load class $className\n";
            return null;
        }
        
        $reflection = new ReflectionClass($className);
        if ($reflection->isInstantiable()) {
            $instance = $reflection->newInstance(...$constructorArgs);
            return $instance;
        }
        
        echo "ERROR: Class $className is not instantiable\n";
        return null;
        
    } catch (Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        return null;
    }
}

echo "This demonstrates how you might create instances in your HtmlBakery class:\n";
echo "In HtmlBakery::processTagNode method:\n";

echo "\n// Example in HtmlBakery context\n";
echo "\$dataProviderClass = \$tagNode['dataProvider'];\n";
echo "if (\$dataProviderClass) {\n";
echo "    \$dataProvider = createClassInstance(\$dataProviderClass);\n";
echo "    if (\$dataProvider) {\n";
echo "        \$data = \$dataProvider->provide(\$context);\n";
echo "        // Then use \$data with renderer\n";
echo "    }\n";
echo "}\n";

?>