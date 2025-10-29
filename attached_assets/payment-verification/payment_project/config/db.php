<?php
namespace App\Config;

use PDO;
use PDOException;

require_once __DIR__ . '/env.php';

class DB {
    public static function pdo(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $host = Env::get('DB_HOST', 'localhost');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', 'payments');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASSWORD', '');
        $charset = 'utf8mb4';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $opt);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'DB connection failed','error'=>$e->getMessage()]);
            exit;
        }
        return $pdo;
    }
}
