<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app/database.php';

$email = isset($argv[1]) ? strtolower(trim($argv[1])) : '';
$password = getenv('PUNTOD_NEW_PASSWORD');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Usage: set PUNTOD_NEW_PASSWORD (12–72 bytes), then run php tools/reset-password.php email@example.com\n");
    exit(1);
}

try {
    $connection = db();
    $connection->beginTransaction();
    $query = $connection->prepare('SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
    $query->execute([$email]);
    $id = $query->fetchColumn();
    if (!$id) {
        $connection->rollBack();
        fwrite(STDERR, "Account not found.\n");
        exit(1);
    }
    $update = $connection->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    $event = $connection->prepare("INSERT INTO account_events (subject_user_id, actor_user_id, action, note) VALUES (?, NULL, 'password_reset', 'CLI reset')");
    $event->execute([$id]);
    $connection->commit();
    echo "Password reset for {$email}. Existing sessions will be signed out.\n";
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    fwrite(STDERR, "Password reset failed. Check the database connection.\n");
    error_log((string) $exception);
    exit(1);
}
