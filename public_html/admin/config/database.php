<?php
// Database connection configuration for Admin Panel
// Uses environment variables from Replit Secrets

// Global database connection (singleton pattern)
$_DB_CONNECTION = null;

function getDBConnection() {
    global $_DB_CONNECTION;
    
    // Return existing connection if available
    if ($_DB_CONNECTION !== null) {
        return $_DB_CONNECTION;
    }
    
    $dbUrl = getenv('DATABASE_URL');
    
    if (!$dbUrl) {
        die('Database configuration not found. DATABASE_URL environment variable is required.');
    }
    
    // Parse DATABASE_URL (format: postgresql://user:password@host:port/database)
    $parts = parse_url($dbUrl);
    
    $host = $parts['host'] ?? 'localhost';
    $port = $parts['port'] ?? 5432;
    $user = $parts['user'] ?? '';
    $pass = $parts['pass'] ?? '';
    $dbname = ltrim($parts['path'] ?? '', '/');
    
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=disable";
    
    try {
        $_DB_CONNECTION = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $_DB_CONNECTION;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die('Database connection failed. Please contact the administrator.');
    }
}

// Helper function to execute queries safely (using shared connection)
function dbQuery($sql, $params = [], $db = null) {
    if ($db === null) {
        $db = getDBConnection();
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Helper function to fetch one row
function dbFetchOne($sql, $params = [], $db = null) {
    $stmt = dbQuery($sql, $params, $db);
    return $stmt->fetch();
}

// Helper function to fetch all rows
function dbFetchAll($sql, $params = [], $db = null) {
    $stmt = dbQuery($sql, $params, $db);
    return $stmt->fetchAll();
}

// Helper function to get last insert ID (uses shared connection)
function dbLastInsertId($sequence = null) {
    $db = getDBConnection();
    return $db->lastInsertId($sequence);
}
