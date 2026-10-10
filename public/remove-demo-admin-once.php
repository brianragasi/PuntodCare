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

try {
    $connection = db();
    $connection->beginTransaction();
    $accounts = $connection->query('SELECT id, email, role, status FROM users ORDER BY id FOR UPDATE')->fetchAll();
    $expected = [
        'admin@puntodcare.local' => 'admin',
        'pilot-admin@example.test' => 'admin',
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
    if (count($accounts) !== 4 || count($ids) !== 4 || $ids['admin@puntodcare.local'] !== (int) $user['id']) {
        throw new UnexpectedValueException('Unexpected account set.');
    }

    $adminId = $ids['admin@puntodcare.local'];
    $demoAdminId = $ids['pilot-admin@example.test'];
    $references = [
        'caretaker_cemeteries' => ['authorized_by'],
        'cemeteries' => ['created_by', 'updated_by'],
        'plots' => ['created_by', 'updated_by'],
        'request_updates' => ['recipient_user_id'],
        'service_offerings' => ['created_by', 'updated_by'],
        'service_price_history' => ['changed_by'],
    ];
    foreach ($references as $table => $columns) {
        foreach ($columns as $column) {
            $statement = $connection->prepare("UPDATE `$table` SET `$column` = ? WHERE `$column` = ?");
            $statement->execute([$adminId, $demoAdminId]);
        }
    }
    $delete = $connection->prepare("DELETE FROM users WHERE id = ? AND email = 'pilot-admin@example.test' AND role = 'admin'");
    $delete->execute([$demoAdminId]);
    if ($delete->rowCount() !== 1 || (int) $connection->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 3) {
        throw new UnexpectedValueException('Account removal was not completed.');
    }

    $connection->commit();
    echo json_encode(['ok' => true, 'total_users' => 3]);
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
