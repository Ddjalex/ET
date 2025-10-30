<?php
/**
 * Deposit Payments Model
 * Handles deposit payment CRUD operations on remote database
 */

require_once __DIR__ . '/../db.php';

class DepositPaymentsModel {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Create new deposit payment
     */
    public function create($data) {
        $required = ['user_id', 'telegram_id', 'amount_usd', 'amount_etb', 'total_etb', 'payment_method'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Missing field: {$field}");
            }
        }
        
        $validMethods = ['telebirr', 'm-pesa', 'cbe', 'bank_transfer'];
        if (!in_array($data['payment_method'], $validMethods)) {
            throw new Exception("Invalid payment_method. Must be one of: " . implode(', ', $validMethods));
        }
        
        $exchangeRate = $data['exchange_rate'] ?? 135.00;
        $depositFeeEtb = $data['deposit_fee_etb'] ?? 0;
        $paymentPhone = $data['payment_phone'] ?? null;
        $transactionNumber = $data['transaction_number'] ?? null;
        $notes = $data['notes'] ?? null;
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO deposit_payments 
                (user_id, telegram_id, amount_usd, amount_etb, exchange_rate, deposit_fee_etb, 
                 total_etb, payment_method, payment_phone, transaction_number, 
                 validation_status, status, notes, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW(), NOW())"
            );
            
            $stmt->execute([
                $data['user_id'],
                $data['telegram_id'],
                $data['amount_usd'],
                $data['amount_etb'],
                $exchangeRate,
                $depositFeeEtb,
                $data['total_etb'],
                $data['payment_method'],
                $paymentPhone,
                $transactionNumber,
                $notes
            ]);
            
            return [
                'deposit_payment_id' => $this->db->lastInsertId(),
                'user_id' => $data['user_id'],
                'telegram_id' => $data['telegram_id'],
                'amount_usd' => $data['amount_usd'],
                'amount_etb' => $data['amount_etb'],
                'exchange_rate' => $exchangeRate,
                'deposit_fee_etb' => $depositFeeEtb,
                'total_etb' => $data['total_etb'],
                'payment_method' => $data['payment_method'],
                'validation_status' => 'pending',
                'status' => 'pending'
            ];
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                throw new Exception("Table 'deposit_payments' does not exist in the database.");
            }
            if (strpos($e->getMessage(), "Unknown column") !== false) {
                preg_match("/Unknown column '([^']+)'/", $e->getMessage(), $matches);
                $column = $matches[1] ?? 'unknown';
                
                $columnType = match($column) {
                    'user_id', 'telegram_id' => 'BIGINT',
                    'amount_usd', 'amount_etb', 'exchange_rate', 'deposit_fee_etb', 'total_etb' => 'DECIMAL(10,2)',
                    'payment_method', 'validation_status', 'status' => 'VARCHAR(50)',
                    'payment_phone', 'transaction_number' => 'VARCHAR(100)',
                    'notes' => 'TEXT',
                    'created_at', 'updated_at' => 'TIMESTAMP',
                    default => 'VARCHAR(255)'
                };
                
                throw new Exception("Missing column in deposit_payments table: {$column}. Execute: ALTER TABLE deposit_payments ADD COLUMN {$column} {$columnType};");
            }
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
}
