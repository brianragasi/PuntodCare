<?php
declare(strict_types=1);

function update_notify(PDO $connection, int $requestId, int $recipientId, int $actorId, string $message): void
{
    if ($recipientId < 1 || $recipientId === $actorId) return;
    $query = $connection->prepare('INSERT INTO request_updates (recipient_user_id, request_id, message) VALUES (?, ?, ?)');
    $query->execute([$recipientId, $requestId, $message]);
}

function update_notify_admins(PDO $connection, int $requestId, int $actorId, string $message): void
{
    $query = $connection->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?");
    $query->execute([$actorId]);
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $adminId) update_notify($connection, $requestId, (int) $adminId, $actorId, $message);
}

function updates_unread_count(int $userId): int
{
    $query = db()->prepare('SELECT COUNT(*) FROM request_updates WHERE recipient_user_id = ? AND read_at IS NULL');
    $query->execute([$userId]);
    return (int) $query->fetchColumn();
}

function updates_list(int $userId): array
{
    $query = db()->prepare('SELECT id, request_id, message, created_at, read_at FROM request_updates WHERE recipient_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100');
    $query->execute([$userId]);
    return $query->fetchAll();
}

function update_open(int $updateId, array $user): ?int
{
    if ($updateId < 1) return null;
    $query = db()->prepare('SELECT request_id FROM request_updates WHERE id = ? AND recipient_user_id = ? LIMIT 1');
    $query->execute([$updateId, $user['id']]);
    $requestId = $query->fetchColumn();
    if (!$requestId) return null;
    $update = db()->prepare('UPDATE request_updates SET read_at = COALESCE(read_at, NOW()) WHERE id = ? AND recipient_user_id = ?');
    $update->execute([$updateId, $user['id']]);
    return request_find((int) $requestId, $user) ? (int) $requestId : 0;
}
