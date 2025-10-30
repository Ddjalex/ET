<?php
/**
 * User Model
 * Handles user CRUD operations on remote database
 */

require_once __DIR__ . '/../db.php';

class UserModel {
    private $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Find user by telegram_id
     */
    public function findByTelegramId($telegramId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM users WHERE telegram_id = ? LIMIT 1"
            );
            $stmt->execute([$telegramId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                throw new Exception("Table 'users' does not exist in the database.");
            }
            if (strpos($e->getMessage(), "Unknown column") !== false) {
                preg_match("/Unknown column '([^']+)'/", $e->getMessage(), $matches);
                $column = $matches[1] ?? 'unknown';
                throw new Exception("Missing column in users table: {$column}. Execute: ALTER TABLE users ADD COLUMN {$column} VARCHAR(255);");
            }
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        $required = ['telegram_id', 'email', 'phone', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing field: {$field}");
            }
        }
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO users 
                (telegram_id, email, phone, first_name, last_name, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())"
            );
            
            $stmt->execute([
                $data['telegram_id'],
                $data['email'],
                $data['phone'],
                $data['first_name'],
                $data['last_name']
            ]);
            
            return [
                'user_id' => $this->db->lastInsertId(),
                'telegram_id' => $data['telegram_id'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'status' => 'active'
            ];
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                throw new Exception("User with this telegram_id or email already exists.");
            }
            if (strpos($e->getMessage(), "Unknown column") !== false) {
                preg_match("/Unknown column '([^']+)'/", $e->getMessage(), $matches);
                $column = $matches[1] ?? 'unknown';
                throw new Exception("Missing column in users table: {$column}. Execute: ALTER TABLE users ADD COLUMN {$column} VARCHAR(255);");
            }
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
    
    /**
     * Register user (create or return existing)
     */
    public function registerUser($data) {
        $existing = $this->findByTelegramId($data['telegram_id']);
        
        if ($existing) {
            return [
                'ok' => true,
                'already_existed' => true,
                'user' => $existing
            ];
        }
        
        $newUser = $this->create($data);
        
        return [
            'ok' => true,
            'already_existed' => false,
            'user_id' => $newUser['user_id'],
            'user' => $newUser
        ];
    }
}
