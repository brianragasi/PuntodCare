<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/app/database.php';

$emails = array_slice($argv, 1);
if (!$emails || count($emails) > 10) exit(1);
foreach ($emails as $email) {
    if (!preg_match('/^[a-z0-9._-]+@example\.test$/', $email)) exit(1);
}
$connection = db();
$removeUser = $connection->prepare('DELETE FROM users WHERE email = ? AND role != ?');
$removeAttempts = $connection->prepare('DELETE FROM login_attempts WHERE email_hash = ?');
foreach ($emails as $email) {
    $removeUser->execute([$email, 'admin']);
    $removeAttempts->execute([hash('sha256', $email)]);
}
echo "Temporary test accounts removed.\n";
