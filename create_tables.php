<?php
/**
 * Table Creation Script
 * Creates users, wallets, and deposit_payments tables if they don't exist
 */

require_once __DIR__ . '/db.php';

try {
    $db = getDbConnection();
    
    echo "=== Creating Tables ===\n\n";
    
    // Create users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            telegram_id BIGINT UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_telegram_id (telegram_id),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'users' created/verified\n";
    
    // Create wallets table
    $db->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            balance_usd DECIMAL(10,2) DEFAULT 0.00,
            balance_etb DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'wallets' created/verified\n";
    
    // Create deposit_payments table
    $db->exec("
        CREATE TABLE IF NOT EXISTS deposit_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            telegram_id BIGINT NOT NULL,
            amount_usd DECIMAL(10,2) NOT NULL,
            amount_etb DECIMAL(10,2) NOT NULL,
            exchange_rate DECIMAL(10,2) DEFAULT 135.00,
            deposit_fee_etb DECIMAL(10,2) DEFAULT 0.00,
            total_etb DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_phone VARCHAR(100),
            transaction_number VARCHAR(100),
            validation_status VARCHAR(50) DEFAULT 'pending',
            status VARCHAR(50) DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_telegram_id (telegram_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Table 'deposit_payments' created/verified\n";
    
    echo "\n=== Verifying Tables ===\n\n";
    
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables in database:\n";
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
    
    echo "\n✅ All tables created successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
