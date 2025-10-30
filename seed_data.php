<?php
/**
 * Data Seeding Script (Prompt B)
 * Inserts sample user and deposit payment
 */

require_once __DIR__ . '/db.php';

try {
    $db = getDbConnection();
    
    echo "=== Seeding Sample Data ===\n\n";
    
    // Insert sample user
    $stmt = $db->prepare("
        INSERT INTO users (telegram_id, email, phone, first_name, last_name, status)
        VALUES (?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ");
    
    $telegramId = 123456789;
    $email = 'test@example.com';
    $phone = '+251912345678';
    $firstName = 'Test';
    $lastName = 'User';
    
    $stmt->execute([$telegramId, $email, $phone, $firstName, $lastName]);
    
    // Get user ID
    $userId = $db->lastInsertId();
    if ($userId == 0) {
        $stmt = $db->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $userId = $stmt->fetchColumn();
    }
    
    echo "✅ User inserted (ID: {$userId})\n";
    
    // Insert sample deposit payment
    $stmt = $db->prepare("
        INSERT INTO deposit_payments (
            user_id, telegram_id, amount_usd, amount_etb, exchange_rate,
            deposit_fee_etb, total_etb, payment_method, payment_phone,
            transaction_number, validation_status, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)
    ");
    
    $amountUsd = 100.00;
    $amountEtb = 13500.00;
    $exchangeRate = 135.00;
    $depositFeeEtb = 0.00;
    $totalEtb = 13500.00;
    $paymentMethod = 'telebirr';
    $paymentPhone = '+251912345678';
    $transactionNumber = 'TXN' . time();
    $notes = 'Sample deposit for testing';
    
    $stmt->execute([
        $userId, $telegramId, $amountUsd, $amountEtb, $exchangeRate,
        $depositFeeEtb, $totalEtb, $paymentMethod, $paymentPhone,
        $transactionNumber, $notes
    ]);
    
    $depositId = $db->lastInsertId();
    echo "✅ Deposit payment inserted (ID: {$depositId})\n";
    
    echo "\n✅ Sample data seeded successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
