<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configPath = dirname(__DIR__) . '/config/local.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Copy config/local.example.php to config/local.php first.\n");
    exit(1);
}
$config = require $configPath;
$settings = $config['database'] ?? [];
$databaseName = $settings['name'] ?? '';
if (!is_string($databaseName) || !preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
    fwrite(STDERR, "Use a database name containing only letters, numbers, and underscores.\n");
    exit(1);
}

try {
    $server = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $settings['host'], (int) $settings['port']),
        (string) $settings['username'],
        (string) $settings['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->exec('USE `' . $databaseName . '`');
    $sql = file_get_contents(dirname(__DIR__) . '/database/001_accounts.sql');
    if ($sql === false) throw new RuntimeException('Migration file is missing.');
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') $server->exec($statement);
    }
    echo "Accounts migration applied to {$databaseName}.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
    exit(1);
}
