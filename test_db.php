<?php
/**
 * Database Connection Test Script
 * Verifies connection to remote MySQL/MariaDB database
 */

require_once __DIR__ . '/db.php';

try {
    $db = getDbConnection();
    
    $stmt = $db->query('SELECT DATABASE() as db_name, VERSION() as version');
    $result = $stmt->fetch();
    
    echo "✅ Database connection successful!\n";
    echo "   Connected to: {$result['db_name']}\n";
    echo "   MySQL version: {$result['version']}\n";
    echo "   Host: " . getenv('DB_HOST') . "\n";
    
} catch (Exception $e) {
    echo "❌ Database connection failed!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    exit(1);
}
