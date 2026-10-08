<?php
/**
 * Simple demonstration of ReflectionClass usage from the current directory
 */

echo "=== SIMPLE REFLECTIONCLASS DEMONSTRATION ===\n\n";

// Show what we have currently
echo "Current directory structure:\n";
echo "Current path: " . getcwd() . "\n";
echo "Core files: " . implode(', ', glob('core/*.php')) . "\n";
echo "Test files: " . implode(', ', glob('test/*.php')) . "\n";

echo "\n=== Creating a simple test ===\n";

// Test that core classes exist and can be loaded properly
try {
    // Check if we can load core classes correctly from current directory
    echo "Loading core/DataProvider.php...\n";
    require_once 'core/DataProvider.php';
    echo "SUCCESS: DataProvider loaded\n";
    
    echo "Loading core/AbstractRenderer.php...\n";
    require_once 'core/AbstractRenderer.php';
    echo "SUCCESS: AbstractRenderer loaded\n";
    
    echo "Loading test/TestDataProvider.php...\n";
    require_once 'test/TestDataProvider.php';
    echo "SUCCESS: TestDataProvider loaded\n";
    
    // Verify that TestDataProvider properly extends DataProvider
    if (class_exists('TestDataProvider')) {
        $reflection = new ReflectionClass('TestDataProvider');
        echo "SUCCESS: TestDataProvider is loaded and can be reflected\n";
        
        if ($reflection->isInstantiable()) {
            echo "SUCCESS: TestDataProvider is instantiable\n";
            
            $instance = $reflection->newInstance();
            echo "SUCCESS: Created instance of TestDataProvider\n";
            
            // Test that it has the required method
            if (method_exists($instance, 'provide')) {
                echo "SUCCESS: Instance has provide() method\n";
                
                // Test it
                $result = $instance->provide([]);
                echo "TestDataProvider->provide() returned: ";
                print_r($result);
            }
        }
    }
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (line " . $e->getLine() . ")\n";
}

echo "\n=== HOW TO CREATE INSTANCES DYNAMICALLY ===\n";

/**
 * The basic pattern for creating instances dynamically using Reflection
 * 
 * In your HtmlBakery class, you would typically do:
 */
function createInstanceViaReflection($className) {
    try {
        // Ensure class exists (in your case, you might need to include the file first)
        if (!class_exists($className)) {
            // This is where you'd include the right file - depends on your framework structure
            echo "Class $className not found, would need to load file\n";
            return null;
        }
        
        $reflection = new ReflectionClass($className);
        
        if ($reflection->isInstantiable()) {
            $instance = $reflection->newInstance();
            echo "Successfully created instance of $className\n";
            return $instance;
        } else {
            echo "Class $className is not instantiable\n";
            return null;
        }
    } catch (Exception $e) {
        echo "Error creating $className: " . $e->getMessage() . "\n";
        return null;
    }
}

echo "In your HtmlBakery context, this is how you'd instantiate a DataProvider:\n";
echo "\$dataProviderClass = \$tagNode['dataProvider'];\n";
echo "if (\$dataProviderClass) {\n";
echo "    \$dataProviderInstance = createInstanceViaReflection(\$dataProviderClass);\n";
echo "    if (\$dataProviderInstance) {\n";
echo "        \$data = \$dataProviderInstance->provide(\$context);\n";
echo "    }\n";
echo "}\n";

echo "\n=== KEY POINTS ABOUT REFLECTION ===\n";
echo "1. ReflectionClass is used to create instances of classes\n";
echo "2. Class must already exist (be loaded) before using Reflection\n"; 
echo "3. Use require_once to load class files before reflection\n";
echo "4. Check isInstantiable() before creating instance\n";
echo "5. Use newInstance() to create new instances\n";

?>