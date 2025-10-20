<?php
/**
 * Simple .env file loader
 * Loads environment variables from .env file into $_ENV and putenv()
 */

function loadEnvFile($filePath = null) {
    if ($filePath === null) {
        $filePath = __DIR__ . '/.env';
    }
    
    if (!file_exists($filePath)) {
        error_log("Warning: .env file not found at: {$filePath}");
        return false;
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        
        // Parse KEY=VALUE or KEY="VALUE"
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            // Set environment variable (both methods for compatibility)
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
    
    return true;
}

// Auto-load when this file is included
loadEnvFile();
