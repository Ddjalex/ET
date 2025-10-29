<?php
// Simple .env loader (optional). In cPanel you can also set env vars in the UI.
// Usage: require __DIR__ . '/env.php';
//        $baseUrl = env('VALIDATION_API_BASE_URL', 'http://127.0.0.1:4001');
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = getenv($key);
        if ($val === false && isset($_ENV[$key])) {
            $val = $_ENV[$key];
        }
        if ($val === false && isset($_SERVER[$key])) {
            $val = $_SERVER[$key];
        }
        return $val !== false ? $val : $default;
    }
}

// Load .env file if present
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "'\""); // strip quotes
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}
