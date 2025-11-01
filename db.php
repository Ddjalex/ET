<?php
/**
 * Replit PostgreSQL Database Connection Module
 * Connects to Replit's built-in PostgreSQL database
 */

function getDbConnection() {
    static $conn = null;
    
    if ($conn !== null) {
        return $conn;
    }
    
    // Replit PostgreSQL connection from DATABASE_URL
    $databaseUrl = getenv('DATABASE_URL');
    
    if (empty($databaseUrl)) {
        throw new Exception('DATABASE_URL environment variable not found');
    }
    
    try {
        // Parse DATABASE_URL into PDO DSN format
        // Format: postgresql://user:password@host:port/database?sslmode=require
        $parsed = parse_url($databaseUrl);
        
        if (!$parsed || !isset($parsed['host'], $parsed['user'], $parsed['path'])) {
            throw new Exception('Invalid DATABASE_URL format');
        }
        
        $host = $parsed['host'];
        $port = $parsed['port'] ?? 5432;
        $dbname = ltrim($parsed['path'], '/');
        $user = $parsed['user'];
        $password = $parsed['pass'] ?? '';
        
        // Build PDO DSN for PostgreSQL
        // Note: Replit PostgreSQL doesn't support SSL in development
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $conn = new PDO($dsn, $user, $password, $options);
        
        return $conn;
        
    } catch (PDOException $e) {
        throw new Exception('PostgreSQL connection error: ' . $e->getMessage());
    }
}

function closeDbConnection() {
    global $conn;
    $conn = null;
}
