<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app/database.php';

$name = isset($argv[1]) ? trim($argv[1]) : '';
$email = isset($argv[2]) ? strtolower(trim($argv[2])) : '';
$password = getenv('PUNTOD_ADMIN_PASSWORD');
if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Usage: set PUNTOD_ADMIN_PASSWORD (12–72 bytes), then run php tools/create-admin.php \"Full Name\" email@example.com\n");
    exit(1);
}

try {
    $connection = db();
    $statement = $connection->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
    $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Administrator created for {$email}.\n";
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "An account with this email already exists.\n");
    } else {
        fwrite(STDERR, "Administrator setup failed. Check the database configuration and migration.\n");
        error_log((string) $exception);
    }
    exit(1);
}
