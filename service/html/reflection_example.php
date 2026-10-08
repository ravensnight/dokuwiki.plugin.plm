<?php
// Example of creating a class instance via reflection

// Let's first see what's in the test files to understand the structure
echo "=== TestDataProvider.php ===\n";
if (file_exists('test/TestDataProvider.php')) {
    echo file_get_contents('test/TestDataProvider.php');
} else {
    echo "TestDataProvider.php not found\n";
}

echo "\n=== TestCustomRenderer.php ===\n";
if (file_exists('test/TestCustomRenderer.php')) {
    echo file_get_contents('test/TestCustomRenderer.php');
} else {
    echo "TestCustomRenderer.php not found\n";
}

// Simple demonstration of ReflectionClass
echo "\n=== Simple Reflection Demo ===\n";

// Check if a class exists
$className = 'TestDataProvider';
if (class_exists($className)) {
    echo "Class $className exists\n";
    
    // Create a ReflectionClass instance
    $reflectionClass = new ReflectionClass($className);
    
    // Check if it's instantiable
    if ($reflectionClass->isInstantiable()) {
        echo "Class is instantiable\n";
        
        // Create an instance
        $instance = $reflectionClass->newInstance();
        echo "Successfully created instance\n";
        var_dump($instance);
    } else {
        echo "Class is not instantiable\n";
    }
} else {
    echo "Class $className does not exist\n";
}

// Example of dynamic instantiation
function createProviderInstance($providerName) {
    try {
        if (!class_exists($providerName)) {
            // Try to include the file if it exists
            $filePath = "test/$providerName.php";
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }
        
        if (class_exists($providerName)) {
            $reflection = new ReflectionClass($providerName);
            if ($reflection->isInstantiable()) {
                return $reflection->newInstance();
            }
        }
        return null;
    } catch (Exception $e) {
        echo "Error creating instance: " . $e->getMessage() . "\n";
        return null;
    }
}

// Try to create an instance if class exists
echo "\n=== Attempting to instantiate ===\n";
$instance = createProviderInstance('TestDataProvider');
if ($instance) {
    echo "Successfully created TestDataProvider instance\n";
} else {
    echo "Failed to create TestDataProvider instance\n";
}
?>