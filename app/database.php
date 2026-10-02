<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $path = dirname(__DIR__) . '/config/local.php';
    if (!is_file($path)) {
        throw new RuntimeException('Database configuration is missing. Copy config/local.example.php to config/local.php.');
    }
    $settings = require $path;
    $database = $settings['database'] ?? null;
    if (!is_array($database)) {
        throw new RuntimeException('Database configuration is invalid.');
    }
    foreach (['host', 'port', 'name', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $database)) {
            throw new RuntimeException('Database configuration is incomplete.');
        }
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $database['host'], (int) $database['port'], $database['name']);
    $connection = new PDO($dsn, (string) $database['username'], (string) $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}
