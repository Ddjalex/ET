<?php
require_once __DIR__ . '/env.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'bingo_online4');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASSWORD', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Recommended: set SQL modes if needed
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $pdo;
}
