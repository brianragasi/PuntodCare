<?php
declare(strict_types=1);

const GRAVE_PHOTO_MAX_BYTES = 5 * 1024 * 1024;
const GRAVE_PHOTO_LIMIT = 4;

function grave_list(int $familyId): array
{
    $query = db()->prepare('SELECT g.*, c.name AS cemetery_name, c.city AS cemetery_city,
        (SELECT p.id FROM grave_photos p WHERE p.grave_id = g.id ORDER BY p.id LIMIT 1) AS cover_photo_id,
        (SELECT COUNT(*) FROM grave_photos p WHERE p.grave_id = g.id) AS photo_count
        FROM grave_profiles g JOIN cemeteries c ON c.id = g.cemetery_id
        WHERE g.family_user_id = ? ORDER BY g.updated_at DESC, g.id DESC LIMIT 100');
    $query->execute([$familyId]);
    return $query->fetchAll();
}

function grave_find(int $graveId, int $familyId): ?array
{
    if ($graveId < 1) return null;
    $query = db()->prepare('SELECT g.*, c.name AS cemetery_name, c.city AS cemetery_city, c.province AS cemetery_province, c.status AS cemetery_status
        FROM grave_profiles g JOIN cemeteries c ON c.id = g.cemetery_id
        WHERE g.id = ? AND g.family_user_id = ? LIMIT 1');
    $query->execute([$graveId, $familyId]);
    return $query->fetch() ?: null;
}

function grave_photos(int $graveId): array
{
    $query = db()->prepare('SELECT id, caption, uploaded_at FROM grave_photos WHERE grave_id = ? ORDER BY id');
    $query->execute([$graveId]);
    return $query->fetchAll();
}

function grave_history(int $graveId): array
{
    $query = db()->prepare('SELECT e.event_type, e.summary, e.occurred_at, u.full_name AS actor_name
        FROM grave_events e JOIN users u ON u.id = e.actor_user_id WHERE e.grave_id = ?
        ORDER BY e.occurred_at DESC, e.id DESC LIMIT 30');
    $query->execute([$graveId]);
    return $query->fetchAll();
}

function grave_date(string $value): bool
{
    if (!preg_match('/^[12][0-9]{3}-[0-9]{2}-[0-9]{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value && $value <= date('Y-m-d');
}

function grave_coordinate(string $value, float $minimum, float $maximum): ?string
{
    if (!preg_match('/^-?[0-9]{1,3}(?:\.[0-9]{1,7})?$/', $value)) return null;
    $coordinate = (float) $value;
    if ($coordinate < $minimum || $coordinate > $maximum) return null;
    return number_format($coordinate, 7, '.', '');
}

function grave_event(PDO $connection, int $graveId, int $actorId, string $type, string $summary): void
{
    $query = $connection->prepare('INSERT INTO grave_events (grave_id, actor_user_id, event_type, summary) VALUES (?, ?, ?, ?)');
    $query->execute([$graveId, $actorId, $type, $summary]);
}

function grave_save(array $values, int $familyId): array
{
    $rawId = $values['id'] ?? '';
    $graveId = catalog_id($rawId);
    if ($rawId !== '' && !$graveId) return ['not_found' => true];
    $existing = $graveId ? grave_find($graveId, $familyId) : null;
    if ($graveId && !$existing) return ['not_found' => true];

    $cemeteryId = catalog_id($values['cemetery_id'] ?? '');
    $cemetery = $cemeteryId ? catalog_record('cemetery', $cemeteryId) : null;
    $name = trim($values['deceased_name'] ?? '');
    $headstone = trim($values['headstone_name'] ?? '');
    $birth = trim($values['birth_date'] ?? '');
    $death = trim($values['death_date'] ?? '');
    $section = trim($values['section_code'] ?? '');
    $block = trim($values['block_code'] ?? '');
    $row = trim($values['row_code'] ?? '');
    $lot = trim($values['lot_code'] ?? '');
    $note = trim($values['location_note'] ?? '');
    $rawLatitude = trim($values['latitude'] ?? '');
    $rawLongitude = trim($values['longitude'] ?? '');
    $latitude = $rawLatitude !== '' ? grave_coordinate($rawLatitude, -90, 90) : null;
    $longitude = $rawLongitude !== '' ? grave_coordinate($rawLongitude, -180, 180) : null;
    $errors = [];

    if (!$cemetery || ($cemetery['status'] !== 'active' && (!$existing || (int) $existing['cemetery_id'] !== $cemeteryId))) $errors['cemetery_id'] = 'Choose an active cemetery.';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors['deceased_name'] = 'Enter the deceased person’s name (2–120 characters).';
    if (mb_strlen($headstone) < 2 || mb_strlen($headstone) > 120) $errors['headstone_name'] = 'Enter the name shown on the headstone.';
    if ($birth !== '' && !grave_date($birth)) $errors['birth_date'] = 'Enter a valid birth date, or leave it blank.';
    if ($death !== '' && !grave_date($death)) $errors['death_date'] = 'Enter a valid death date, or leave it blank.';
    if ($birth !== '' && $death !== '' && $birth > $death) $errors['death_date'] = 'Death date must be after the birth date.';
    if (mb_strlen($section) < 1 || mb_strlen($section) > 80) $errors['section_code'] = 'Enter the cemetery section.';
    if (mb_strlen($block) > 80) $errors['block_code'] = 'Keep the block under 80 characters.';
    if (mb_strlen($row) > 80) $errors['row_code'] = 'Keep the row under 80 characters.';
    if (mb_strlen($lot) < 1 || mb_strlen($lot) > 80) $errors['lot_code'] = 'Enter the lot or grave number.';
    if (mb_strlen($note) > 500) $errors['location_note'] = 'Keep the finding note under 500 characters.';
    if (($rawLatitude === '') !== ($rawLongitude === '') || ($rawLatitude !== '' && $latitude === null) || ($rawLongitude !== '' && $longitude === null)) $errors['coordinates'] = 'Enter valid latitude and longitude together, or leave both blank.';
    if ($errors) return ['errors' => $errors];

    $connection = db();
    $connection->beginTransaction();
    try {
        if ($graveId) {
            $query = $connection->prepare('UPDATE grave_profiles SET cemetery_id = ?, deceased_name = ?, headstone_name = ?, birth_date = ?, death_date = ?, section_code = ?, block_code = ?, row_code = ?, lot_code = ?, location_note = ?, latitude = ?, longitude = ? WHERE id = ? AND family_user_id = ?');
            $query->execute([$cemeteryId, $name, $headstone, $birth ?: null, $death ?: null, $section, $block, $row, $lot, $note, $latitude, $longitude, $graveId, $familyId]);
            grave_event($connection, $graveId, $familyId, 'updated', 'Grave details updated');
        } else {
            $query = $connection->prepare('INSERT INTO grave_profiles (family_user_id, cemetery_id, deceased_name, headstone_name, birth_date, death_date, section_code, block_code, row_code, lot_code, location_note, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$familyId, $cemeteryId, $name, $headstone, $birth ?: null, $death ?: null, $section, $block, $row, $lot, $note, $latitude, $longitude]);
            $graveId = (int) $connection->lastInsertId();
            grave_event($connection, $graveId, $familyId, 'created', 'Grave profile registered');
        }
        $connection->commit();
        return ['id' => $graveId];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function grave_photo_directory(): string
{
    return dirname(__DIR__) . '/storage/grave-photos';
}

function redirect_to_grave(int $graveId): never
{
    header('Location: ?page=grave&id=' . $graveId, true, 303);
    exit;
}

function grave_upload_photo(int $graveId, int $familyId, ?array $file, string $caption): array
{
    if (!grave_find($graveId, $familyId)) return ['not_found' => true];
    $caption = trim($caption);
    if (mb_strlen($caption) > 200) return ['error' => 'Keep the photo caption under 200 characters.'];
    if (!$file || !isset($file['error']) || is_array($file['error'])) return ['error' => 'Choose one reference photo.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE ? 'The photo is too large.' : 'The photo could not be uploaded.'];
    $temporary = $file['tmp_name'] ?? '';
    $size = $file['size'] ?? 0;
    if (!is_string($temporary) || !is_int($size) || $size < 1 || $size > GRAVE_PHOTO_MAX_BYTES || !is_uploaded_file($temporary)) return ['error' => 'Choose a JPEG, PNG, or WebP photo under 5 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $image = @getimagesize($temporary);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !is_array($image) || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1 || $image[0] > 6000 || $image[1] > 6000 || $image[0] * $image[1] > 16000000) return ['error' => 'Use a valid JPEG, PNG, or WebP photo up to 16 megapixels.'];

    $directory = grave_photo_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cannot create private photo storage.');
    $storageName = bin2hex(random_bytes(24));
    $path = $directory . '/' . $storageName;
    $connection = db();
    $connection->beginTransaction();
    try {
        $lock = $connection->prepare('SELECT id FROM grave_profiles WHERE id = ? AND family_user_id = ? FOR UPDATE');
        $lock->execute([$graveId, $familyId]);
        if (!$lock->fetchColumn()) {
            $connection->rollBack();
            return ['not_found' => true];
        }
        $count = $connection->prepare('SELECT COUNT(*) FROM grave_photos WHERE grave_id = ?');
        $count->execute([$graveId]);
        if ((int) $count->fetchColumn() >= GRAVE_PHOTO_LIMIT) {
            $connection->rollBack();
            return ['error' => 'This grave already has four reference photos. Remove one before uploading another.'];
        }
        if (!move_uploaded_file($temporary, $path)) throw new RuntimeException('Private photo storage is unavailable.');
        $query = $connection->prepare('INSERT INTO grave_photos (grave_id, storage_name, mime_type, byte_size, caption, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)');
        $query->execute([$graveId, $storageName, $mime, $size, $caption, $familyId]);
        $photoId = (int) $connection->lastInsertId();
        grave_event($connection, $graveId, $familyId, 'photo_added', 'Reference photo added');
        $connection->commit();
        return ['id' => $photoId];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if (is_file($path)) unlink($path);
        throw $exception;
    }
}

function grave_remove_photo(int $photoId, int $familyId): array
{
    $connection = db();
    $connection->beginTransaction();
    try {
        $query = $connection->prepare('SELECT p.grave_id, p.storage_name FROM grave_photos p JOIN grave_profiles g ON g.id = p.grave_id WHERE p.id = ? AND g.family_user_id = ? FOR UPDATE');
        $query->execute([$photoId, $familyId]);
        $photo = $query->fetch();
        if (!$photo) {
            $connection->rollBack();
            return ['not_found' => true];
        }
        $delete = $connection->prepare('DELETE FROM grave_photos WHERE id = ?');
        $delete->execute([$photoId]);
        grave_event($connection, (int) $photo['grave_id'], $familyId, 'photo_removed', 'Reference photo removed');
        $connection->commit();
        if (preg_match('/^[a-f0-9]{48}$/', $photo['storage_name'])) {
            $path = grave_photo_directory() . '/' . $photo['storage_name'];
            if (is_file($path) && !unlink($path)) error_log('Could not remove a deleted grave photo from private storage.');
        }
        return ['grave_id' => (int) $photo['grave_id']];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function grave_send_photo(int $photoId, array $user): bool
{
    if ($photoId < 1) return false;
    $query = db()->prepare('SELECT p.storage_name, p.mime_type, p.grave_id, g.family_user_id FROM grave_photos p JOIN grave_profiles g ON g.id = p.grave_id WHERE p.id = ? LIMIT 1');
    $query->execute([$photoId]);
    $photo = $query->fetch();
    if (!$photo || !preg_match('/^[a-f0-9]{48}$/', $photo['storage_name']) || !in_array($photo['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) return false;
    $allowed = $user['role'] === 'family' && (int) $photo['family_user_id'] === (int) $user['id'];
    if (!$allowed && $user['role'] === 'caretaker' && $user['status'] === 'verified') {
        $access = db()->prepare("SELECT 1 FROM service_requests r JOIN caretaker_cemeteries a ON a.caretaker_id = r.caretaker_id AND a.cemetery_id = r.cemetery_id WHERE r.grave_id = ? AND r.caretaker_id = ? AND r.status IN ('assigned', 'accepted', 'in_progress', 'awaiting_review') LIMIT 1");
        $access->execute([$photo['grave_id'], $user['id']]);
        $allowed = (bool) $access->fetchColumn();
    }
    if (!$allowed) return false;
    $path = grave_photo_directory() . '/' . $photo['storage_name'];
    if (!is_file($path)) return false;
    header('Content-Type: ' . $photo['mime_type']);
    header('Content-Disposition: inline; filename="grave-reference"');
    header('Content-Length: ' . filesize($path));
    header("Content-Security-Policy: default-src 'none'; sandbox");
    session_write_close();
    readfile($path);
    return true;
}
