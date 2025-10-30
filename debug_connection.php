<?php
/**
 * Detailed Database Connection Debug Script
 */

$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$ssl = getenv('DB_SSL') ?: 'false';

echo "=== Connection Parameters ===\n";
echo "Host: {$host}\n";
echo "Port: {$port}\n";
echo "Database: {$dbname}\n";
echo "User: {$user}\n";
echo "Password: " . (empty($password) ? 'NOT SET' : 'SET (' . strlen($password) . ' chars)') . "\n";
echo "SSL: {$ssl}\n\n";

echo "=== Attempting Connection ===\n";

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
echo "DSN: {$dsn}\n\n";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
    echo "✅ SUCCESS: Connected to database!\n";
    
    $stmt = $pdo->query('SELECT DATABASE() as db_name, VERSION() as version');
    $result = $stmt->fetch();
    echo "   Database: {$result['db_name']}\n";
    echo "   Version: {$result['version']}\n";
    
} catch (PDOException $e) {
    echo "❌ FAILED: Connection error\n";
    echo "   Error Code: " . $e->getCode() . "\n";
    echo "   Error Message: " . $e->getMessage() . "\n";
    echo "\n";
    
    // Check specific error codes
    $code = $e->getCode();
    if ($code == 1045) {
        echo "⚠️  Error 1045: Access denied for user\n";
        echo "   - Check DB_USER and DB_PASSWORD are correct\n";
        echo "   - Verify user has remote access permissions\n";
    } elseif ($code == 2002) {
        echo "⚠️  Error 2002: Connection refused\n";
        echo "   - Check DB_HOST is reachable\n";
        echo "   - Verify remote MySQL is enabled in cPanel\n";
    } elseif ($code == 1044) {
        echo "⚠️  Error 1044: Access denied to database\n";
        echo "   - User exists but lacks database permissions\n";
    }
    exit(1);
}
