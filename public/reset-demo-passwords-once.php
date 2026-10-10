<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/security.php';
require_once dirname(__DIR__) . '/app/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false]);
    exit;
}

start_secure_session();
$user = current_user();
if (!$user || $user['role'] !== 'admin' || $user['email'] !== 'admin@puntodcare.local' || !valid_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$passwords = [
    'pilot-family@example.test' => $_POST['family_password'] ?? null,
    'pilot-caretaker@example.test' => $_POST['caretaker_password'] ?? null,
];
foreach ($passwords as $password) {
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\n")) {
        http_response_code(422);
        echo json_encode(['ok' => false]);
        exit;
    }
}
if (hash_equals($passwords['pilot-family@example.test'], $passwords['pilot-caretaker@example.test'])) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $connection = db();
    $connection->beginTransaction();
    $accounts = $connection->query('SELECT id, email, role FROM users ORDER BY id FOR UPDATE')->fetchAll();
    $expected = [
        'admin@puntodcare.local' => 'admin',
        'pilot-family@example.test' => 'family',
        'pilot-caretaker@example.test' => 'caretaker',
    ];
    $ids = [];
    foreach ($accounts as $account) {
        if (($expected[$account['email']] ?? null) !== $account['role']) {
            throw new UnexpectedValueException('Unexpected account set.');
        }
        $ids[$account['email']] = (int) $account['id'];
    }
    if (count($accounts) !== 3 || count($ids) !== 3 || $ids['admin@puntodcare.local'] !== (int) $user['id']) {
        throw new UnexpectedValueException('Unexpected account set.');
    }

    $update = $connection->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $event = $connection->prepare("INSERT INTO account_events (subject_user_id, actor_user_id, action, note) VALUES (?, ?, 'password_reset', 'Pilot demo password updated by administrator')");
    foreach ($passwords as $email => $password) {
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $ids[$email]]);
        $event->execute([$ids[$email], (int) $user['id']]);
    }
    $connection->commit();
    echo json_encode(['ok' => true, 'updated' => 2]);
} catch (UnexpectedValueException $exception) {
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    http_response_code(409);
    echo json_encode(['ok' => false, 'reason' => 'Account set differs from the expected pilot accounts.']);
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    error_log((string) $exception);
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
