<?php

/**
 * Registry for Provider instances to optimize performance by caching 
 * reflection-instantiated classes.
 */
class ProviderRegistry {
    private static $instances = [];

    /**
     * Get a provider instance, instantiating it via reflection if not already cached.
     * 
     * @param string $className The fully qualified class name of the provider.
     * @return Provider
     */
    public static function getProvider(string $className): Provider {
        if (!isset(self::$instances[$className])) {
            if (!class_exists($className)) {
                throw new Exception("Provider class $className does not exist.");
            }
            self::$instances[$className] = new $className();
        }
        return self::$instances[$className];
    }

    /**
     * Clear the registry.
     */
    public static function clear(): void {
        self::$instances = [];
    }
}
