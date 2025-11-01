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
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $conn = new PDO($databaseUrl, null, null, $options);
        
        return $conn;
        
    } catch (PDOException $e) {
        throw new Exception('PostgreSQL connection error: ' . $e->getMessage());
    }
}

function closeDbConnection() {
    global $conn;
    $conn = null;
}
