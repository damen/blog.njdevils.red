<?php

/**
 * Simple .env file loader
 * 
 * Loads environment variables from .env file without external dependencies.
 * Does not overwrite existing $_ENV values.
 */
class Env
{
    private static bool $loaded = false;
    
    /**
     * Load .env file from specified path
     */
    public static function load(string $path = '.env'): void
    {
        if (self::$loaded) {
            return;
        }
        
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Parse KEY=VALUE
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove quotes if present
            if (($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Don't overwrite existing environment variables
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with optional default
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has(string $key): bool
    {
        return isset($_ENV[$key]);
    }
}