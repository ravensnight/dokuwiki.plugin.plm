<?php
/**
 * Demonstration of PHP ReflectionClass usage
 */

// Basic approach to create instances with proper includes
echo "=== DEMONSTRATING REFLECTIONCLASS USAGE ===\n\n";

// Method 1: Direct ReflectionClass usage (if class is already autoloaded)
echo "Method 1: Direct ReflectionClass approach\n";
try {
    // Check if class exists before trying to create it
    $className = 'TestDataProvider';
    
    if (class_exists($className)) {
        echo "Class '$className' already loaded\n";
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstance();
        echo "Successfully created instance\n";
    } else {
        echo "Class '$className' not yet loaded\n";
        
        // Load the file manually first
        $testProviderPath = 'test/TestDataProvider.php';
        if (file_exists($testProviderPath)) {
            require_once $testProviderPath;
            echo "Loaded TestDataProvider file\n";
            
            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);
                $instance = $reflection->newInstance();
                echo "Successfully created instance after loading file\n";
            } else {
                echo "Class still doesn't exist after loading file\n";
            }
        } else {
            echo "TestDataProvider file not found\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Method 2: Safe instantiation function\n";

function safeInstantiate($className, $constructorArgs = []) {
    try {
        // Check if the class exists first
        if (!class_exists($className)) {
            // Try to include the file that should contain it
            // Note: You need to know the proper path
            $filePath = '';
            
            // For our specific case, check known patterns
            if (strpos($className, 'DataProvider') !== false) {
                $filePath = "test/{$className}.php";
            } elseif (strpos($className, 'Renderer') !== false) {
                $filePath = "test/{$className}.php";
            } elseif (strpos($className, 'Test') !== false) {
                $filePath = "test/{$className}.php";
            } else {
                $filePath = "core/{$className}.php";
            }
            
            if (file_exists($filePath)) {
                require_once $filePath;
                echo "Loaded file: $filePath\n";
            }
        }
        
        // Try to create the reflection instance
        if (class_exists($className)) {
            $reflectionClass = new ReflectionClass($className);
            
            // Check if it's instantiable (has a public constructor)
            if ($reflectionClass->isInstantiable()) {
                // Create instance with arguments if provided
                $instance = $reflectionClass->newInstance(...$constructorArgs);
                echo "Successfully created instance of $className\n";
                return $instance;
            } else {
                echo "Class $className is not instantiable\n";
                return null;
            }
        } else {
            echo "Class $className still does not exist\n";
            return null;
        }
        
    } catch (Exception $e) {
        echo "Error creating instance: " . $e->getMessage() . "\n";
        return null;
    }
}

// Example usage for a DataProvider:
echo "\nTrying to create TestDataProvider instance:\n";
$instance1 = safeInstantiate('TestDataProvider');

// Example usage for a Renderer:
echo "\nTrying to create TestCustomRenderer instance:\n";
$instance2 = safeInstantiate('TestCustomRenderer');

echo "\n=== GENERATING REFLECTION EXAMPLE CODE ===\n";
echo "Here's how to properly create classes dynamically in your HtmlBakery context:\n\n";

// Create example code that shows how this would work in your use case
$reflectionExampleCode = '<?php
// In HtmlBakery::processTagNode method, you would have:
function instantiateProvider($providerName, $context = null) {
    try {
        // Check if provider class already exists
        if (!class_exists($providerName)) {
            // Handle the path logic based on where your providers are defined
            // This might be dynamic or you might need to load files in a specific way
            if (file_exists("core/{$providerName}.php")) {
                require_once "core/{$providerName}.php";
            } elseif (file_exists("test/{$providerName}.php")) {
                require_once "test/{$providerName}.php";
            } else {
                // Try to find the class via include path or loader
                // This depends on your framework structure
                echo "Provider file not found: {$providerName}\n";
                return null;
            }
        }
        
        // Create reflection and instantiate
        if (class_exists($providerName)) {
            $reflection = new ReflectionClass($providerName);
            if ($reflection->isInstantiable()) {
                return $reflection->newInstance();
            }
        }
        
        return null;
    } catch (Exception $e) {
        echo "Error in instantiateProvider: " . $e->getMessage() . "\n";
        return null;
    }
}

// Usage in your framework:
$dataProviderInstance = instantiateProvider("TestDataProvider");
if ($dataProviderInstance !== null) {
    $data = $dataProviderInstance->provide($context);
}
?>';

echo $reflectionExampleCode;

?>