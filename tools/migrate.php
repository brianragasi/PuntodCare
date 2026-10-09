<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/private.php';

$configPath = puntod_config_path();
if (!is_file($configPath)) {
    fwrite(STDERR, "Create the private local.php database configuration first.\n");
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
    try {
        $server->exec('USE `' . $databaseName . '`');
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1049) throw $exception;
        $server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $server->exec('USE `' . $databaseName . '`');
    }
    $files = glob(dirname(__DIR__) . '/database/[0-9]*.sql');
    if (!$files) throw new RuntimeException('Migration files are missing.');
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) throw new RuntimeException('Cannot read migration file.');
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') $server->exec($statement);
        }
        echo 'Applied ' . basename($file) . ".\n";
    }
    echo "Migrations applied to {$databaseName}.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
    exit(1);
}
