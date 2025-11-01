<?php
/**
 * Remote MySQL/MariaDB Database Connection Module
 * Connects to cPanel-hosted database using environment secrets
 */

function getDbConnection() {
    static $conn = null;
    
    if ($conn !== null) {
        return $conn;
    }
    
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    $ssl = getenv('DB_SSL') ?: 'false';
    
    if (empty($host) || empty($dbname) || empty($user)) {
        throw new Exception('Missing required database credentials: DB_HOST, DB_NAME, or DB_USER');
    }
    
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        if (strtolower($ssl) === 'true') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        $conn = new PDO($dsn, $user, $password, $options);
        
        return $conn;
        
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        
        if (strpos($errorMsg, 'Access denied') !== false) {
            throw new Exception('Database access denied. Check DB_USER and DB_PASSWORD.');
        } elseif (strpos($errorMsg, "Can't connect") !== false || strpos($errorMsg, 'Connection refused') !== false) {
            throw new Exception('Cannot connect to database. Check DB_HOST and DB_PORT.');
        } elseif (strpos($errorMsg, 'Unknown database') !== false) {
            throw new Exception('Database not found. Check DB_NAME.');
        } else {
            throw new Exception('Database connection error: ' . $errorMsg);
        }
    }
}

function closeDbConnection() {
    global $conn;
    $conn = null;
}
