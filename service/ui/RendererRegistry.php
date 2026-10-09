<?php

/**
 * Registry for Renderer instances to optimize performance by caching 
 * reflection-instantiated classes.
 */
class RendererRegistry {
    private static $instances = [];

    /**
     * Get a renderer instance, instantiating it via reflection if not already cached.
     * 
     * @param string $className The fully qualified class name of the renderer.
     * @return Renderer
     */
    public static function getRenderer(string $className): Renderer {
        if (!isset(self::$instances[$className])) {
            if (!class_exists($className)) {
                throw new Exception("Renderer class $className does not exist.");
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
