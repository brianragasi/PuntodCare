<?php
declare(strict_types=1);

const REQUEST_EVIDENCE_MAX_BYTES = 5 * 1024 * 1024;
const REQUEST_EVIDENCE_PER_STAGE = 3;

function request_round(PDO $connection, int $requestId): int
{
    $query = $connection->prepare("SELECT COUNT(*) FROM request_events WHERE request_id = ? AND from_status IN ('in_progress', 'issue_reported') AND to_status = 'requested'");
    $query->execute([$requestId]);
    return 1 + (int) $query->fetchColumn();
}

function request_evidence_list(int $requestId): array
{
    $query = db()->prepare("SELECT e.id, e.round_no, e.stage, e.caption, e.uploaded_at, u.full_name AS uploader_name FROM request_evidence e JOIN users u ON u.id = e.uploaded_by WHERE e.request_id = ? ORDER BY e.round_no DESC, FIELD(e.stage, 'before', 'after'), e.id");
    $query->execute([$requestId]);
    return $query->fetchAll();
}

function request_evidence_directory(): string
{
    return dirname(__DIR__) . '/storage/request-evidence';
}

function request_evidence_upload(int $requestId, array $user, string $stage, ?array $file, string $caption): array
{
    if ($user['role'] !== 'caretaker' || !request_find($requestId, $user)) return ['not_found' => true];
    if (!in_array($stage, ['before', 'after'], true)) return ['error' => 'Choose before or after evidence.'];
    $caption = trim($caption);
    if (mb_strlen($caption) > 200) return ['error' => 'Keep the caption under 200 characters.'];
    if (!$file || !isset($file['error']) || is_array($file['error'])) return ['error' => 'Choose one evidence photo.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'The photo is too large.' : 'The photo could not be uploaded.'];
    $temporary = $file['tmp_name'] ?? '';
    $size = $file['size'] ?? 0;
    if (!is_string($temporary) || !is_int($size) || $size < 1 || $size > REQUEST_EVIDENCE_MAX_BYTES || !is_uploaded_file($temporary)) return ['error' => 'Choose a JPEG, PNG, or WebP photo under 5 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $image = @getimagesize($temporary);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !is_array($image) || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1 || $image[0] > 6000 || $image[1] > 6000 || $image[0] * $image[1] > 16000000) return ['error' => 'Use a valid JPEG, PNG, or WebP photo up to 16 megapixels.'];

    $directory = request_evidence_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cannot create private evidence storage.');
    $storageName = bin2hex(random_bytes(24));
    $path = $directory . '/' . $storageName;
    $connection = db();
    $connection->beginTransaction();
    try {
        $query = $connection->prepare('SELECT cemetery_id, caretaker_id, status FROM service_requests WHERE id = ? FOR UPDATE');
        $query->execute([$requestId]);
        $request = $query->fetch();
        if (!$request || (int) $request['caretaker_id'] !== (int) $user['id']) {
            $connection->rollBack();
            return ['not_found' => true];
        }
        if ($request['status'] !== 'in_progress') {
            $connection->rollBack();
            return ['error' => 'Photos can be changed only while the job is in progress.'];
        }
        $access = $connection->prepare("SELECT 1 FROM users u JOIN caretaker_cemeteries a ON a.caretaker_id = u.id WHERE u.id = ? AND u.role = 'caretaker' AND u.status = 'verified' AND a.cemetery_id = ? LIMIT 1 FOR UPDATE");
        $access->execute([$user['id'], $request['cemetery_id']]);
        if (!$access->fetchColumn()) {
            $connection->rollBack();
            return ['error' => 'Your verification or cemetery access has changed. Contact an administrator.'];
        }
        $round = request_round($connection, $requestId);
        $counts = $connection->prepare('SELECT stage, COUNT(*) AS total FROM request_evidence WHERE request_id = ? AND round_no = ? GROUP BY stage');
        $counts->execute([$requestId, $round]);
        $stageCounts = ['before' => 0, 'after' => 0];
        foreach ($counts->fetchAll() as $entry) $stageCounts[$entry['stage']] = (int) $entry['total'];
        if ($stageCounts[$stage] >= REQUEST_EVIDENCE_PER_STAGE) {
            $connection->rollBack();
            return ['error' => 'This stage already has three photos. Remove one before adding another.'];
        }
        if ($stage === 'after' && $stageCounts['before'] < 1) {
            $connection->rollBack();
            return ['error' => 'Add a before photo before an after photo.'];
        }
        if ($stage === 'before' && $stageCounts['after'] > 0) {
            $connection->rollBack();
            return ['error' => 'Before photos cannot be added after after photos.'];
        }
        if (!move_uploaded_file($temporary, $path)) throw new RuntimeException('Private evidence storage is unavailable.');
        $insert = $connection->prepare('INSERT INTO request_evidence (request_id, round_no, stage, storage_name, mime_type, byte_size, caption, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$requestId, $round, $stage, $storageName, $mime, $size, $caption, $user['id']]);
        $evidenceId = (int) $connection->lastInsertId();
        request_event($connection, $requestId, (int) $user['id'], 'in_progress', 'in_progress', ucfirst($stage) . ' evidence photo added for round ' . $round . '.');
        $connection->commit();
        return ['id' => $evidenceId];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if (is_file($path)) unlink($path);
        throw $exception;
    }
}

function request_evidence_remove(int $evidenceId, array $user): array
{
    if ($user['role'] !== 'caretaker' || $user['status'] !== 'verified') return ['not_found' => true];
    $connection = db();
    $connection->beginTransaction();
    try {
        $query = $connection->prepare('SELECT e.request_id, e.round_no, e.stage, e.storage_name, e.uploaded_by, r.cemetery_id, r.caretaker_id, r.status FROM request_evidence e JOIN service_requests r ON r.id = e.request_id WHERE e.id = ? FOR UPDATE');
        $query->execute([$evidenceId]);
        $item = $query->fetch();
        if (!$item || (int) $item['caretaker_id'] !== (int) $user['id'] || (int) $item['uploaded_by'] !== (int) $user['id']) {
            $connection->rollBack();
            return ['not_found' => true];
        }
        $access = $connection->prepare('SELECT 1 FROM caretaker_cemeteries WHERE caretaker_id = ? AND cemetery_id = ? LIMIT 1');
        $access->execute([$user['id'], $item['cemetery_id']]);
        if (!$access->fetchColumn() || $item['status'] !== 'in_progress' || (int) $item['round_no'] !== request_round($connection, (int) $item['request_id'])) {
            $connection->rollBack();
            return ['error' => 'This evidence photo can no longer be removed.'];
        }
        $delete = $connection->prepare('DELETE FROM request_evidence WHERE id = ?');
        $delete->execute([$evidenceId]);
        request_event($connection, (int) $item['request_id'], (int) $user['id'], 'in_progress', 'in_progress', ucfirst($item['stage']) . ' evidence photo removed.');
        $connection->commit();
        if (preg_match('/^[a-f0-9]{48}$/', $item['storage_name'])) {
            $path = request_evidence_directory() . '/' . $item['storage_name'];
            if (is_file($path) && !unlink($path)) error_log('Could not remove deleted request evidence from private storage.');
        }
        return ['request_id' => (int) $item['request_id']];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function request_evidence_send(int $evidenceId, array $user): bool
{
    if ($evidenceId < 1) return false;
    $query = db()->prepare('SELECT request_id, storage_name, mime_type FROM request_evidence WHERE id = ? LIMIT 1');
    $query->execute([$evidenceId]);
    $item = $query->fetch();
    if (!$item || !request_find((int) $item['request_id'], $user) || !preg_match('/^[a-f0-9]{48}$/', $item['storage_name']) || !in_array($item['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) return false;
    $path = request_evidence_directory() . '/' . $item['storage_name'];
    if (!is_file($path)) return false;
    header('Content-Type: ' . $item['mime_type']);
    header('Content-Disposition: inline; filename="care-evidence"');
    header('Content-Length: ' . filesize($path));
    header("Content-Security-Policy: default-src 'none'; sandbox");
    session_write_close();
    readfile($path);
    return true;
}
