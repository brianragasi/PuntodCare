<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$name = isset($argv[1]) ? trim($argv[1]) : '';
$email = isset($argv[2]) ? strtolower(trim($argv[2])) : '';
if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/prepare-live-admin.php \"Full Name\" email@example.com\n");
    exit(1);
}

$root = dirname(__DIR__);
$sqlPath = $root . '/storage/live-admin-import.sql';
$credentialsPath = $root . '/storage/live-admin-credentials.json';
if (is_file($sqlPath) || is_file($credentialsPath)) {
    fwrite(STDERR, "Live administrator setup files already exist. Review them before generating new credentials.\n");
    exit(1);
}

$sqlText = static fn (string $value): string => 'CONVERT(0x' . bin2hex($value) . ' USING utf8mb4)';
$password = bin2hex(random_bytes(18));
$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = 'INSERT INTO users (full_name, email, password_hash, role, status) VALUES ('
    . $sqlText($name) . ', ' . $sqlText($email) . ', ' . $sqlText($hash) . ", 'admin', 'active');\n";
$credentials = json_encode(['email' => $email, 'password' => $password], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

try {
    if (!is_dir($root . '/storage') || file_put_contents($sqlPath, $sql, LOCK_EX) === false || file_put_contents($credentialsPath, $credentials, LOCK_EX) === false) {
        throw new RuntimeException('Could not write private administrator setup files.');
    }
    @chmod($sqlPath, 0600);
    @chmod($credentialsPath, 0600);
    echo "Generated storage/live-admin-import.sql for phpMyAdmin and storage/live-admin-credentials.json for your private records. Do not upload either file to the website.\n";
} catch (Throwable $exception) {
    if (is_file($sqlPath)) unlink($sqlPath);
    if (is_file($credentialsPath)) unlink($credentialsPath);
    fwrite(STDERR, "Administrator preparation failed: {$exception->getMessage()}\n");
    exit(1);
}
