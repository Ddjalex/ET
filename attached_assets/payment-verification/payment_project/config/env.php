<?php
namespace App\Config;

class Env {
    private static $loaded = false;
    private static $vars = [];

    public static function load(string $path): void {
        if (self::$loaded) return;
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (!str_contains($line, '=')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                $v = trim($v, "\"'");
                self::$vars[$k] = $v;
                putenv("$k=$v");
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            $envPath = dirname(__DIR__) . '/.env'; // project root .env
            self::load($envPath);
        }
        $val = getenv($key);
        if ($val === false && isset(self::$vars[$key])) {
            $val = self::$vars[$key];
        }
        return $val !== false ? $val : $default;
    }
}
