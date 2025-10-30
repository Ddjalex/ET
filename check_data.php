<?php
/**
 * Data Check Script (Prompt B)
 * Queries and displays data in JSON format
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    $db = getDbConnection();
    
    $results = [];
    
    // Query user by telegram_id
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ? LIMIT 1");
    $stmt->execute([123456789]);
    $user = $stmt->fetch();
    
    $results['user'] = $user ?: null;
    
    // Query last 5 deposit_payments
    $stmt = $db->query("
        SELECT * FROM deposit_payments 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $deposits = $stmt->fetchAll();
    
    $results['last_5_deposits'] = $deposits;
    $results['total_deposits'] = count($deposits);
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit(1);
}
