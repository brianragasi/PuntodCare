<?php
declare(strict_types=1);

function catalog_id(string $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : $id;
}

function catalog_money(int $centavos): string
{
    return '₱' . number_format($centavos / 100, 2);
}

function catalog_price_centavos(string $price): ?int
{
    if (!preg_match('/^[1-9][0-9]{0,5}(?:\.[0-9]{1,2})?$/', $price)) return null;
    $parts = explode('.', $price, 2);
    return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
}

function catalog_cemeteries(): array
{
    return db()->query('SELECT c.*, (SELECT COUNT(*) FROM plots p WHERE p.cemetery_id = c.id) AS plot_count, (SELECT COUNT(*) FROM service_offerings s WHERE s.cemetery_id = c.id) AS service_count FROM cemeteries c ORDER BY c.status ASC, c.name ASC, c.city ASC')->fetchAll();
}

function catalog_plots(int $cemeteryId = 0, string $search = ''): array
{
    $sql = 'SELECT p.*, c.name AS cemetery_name, c.city AS cemetery_city, c.status AS cemetery_status FROM plots p JOIN cemeteries c ON c.id = p.cemetery_id WHERE (? = 0 OR p.cemetery_id = ?) AND (? = \'\' OR p.section_code LIKE ? OR p.block_code LIKE ? OR p.row_code LIKE ? OR p.lot_code LIKE ?) ORDER BY c.name, p.section_code, p.block_code, p.row_code, p.lot_code LIMIT 100';
    $like = '%' . mb_substr($search, 0, 80) . '%';
    $query = db()->prepare($sql);
    $query->execute([$cemeteryId, $cemeteryId, $search, $like, $like, $like, $like]);
    return $query->fetchAll();
}

function catalog_services(int $cemeteryId = 0): array
{
    $query = db()->prepare('SELECT s.*, c.name AS cemetery_name, c.city AS cemetery_city, c.status AS cemetery_status FROM service_offerings s JOIN cemeteries c ON c.id = s.cemetery_id WHERE (? = 0 OR s.cemetery_id = ?) ORDER BY c.name, s.name LIMIT 100');
    $query->execute([$cemeteryId, $cemeteryId]);
    return $query->fetchAll();
}

function catalog_service_history(int $serviceId): array
{
    $query = db()->prepare('SELECT h.price_centavos, h.changed_at, u.full_name AS changed_by_name FROM service_price_history h JOIN users u ON u.id = h.changed_by WHERE h.service_id = ? ORDER BY h.changed_at DESC, h.id DESC LIMIT 10');
    $query->execute([$serviceId]);
    return $query->fetchAll();
}

function catalog_families(string $search = ''): array
{
    $like = '%' . mb_substr($search, 0, 80) . '%';
    $query = db()->prepare("SELECT id, full_name, email, created_at FROM users WHERE role = 'family' AND (? = '' OR full_name LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT 100");
    $query->execute([$search, $like, $like]);
    return $query->fetchAll();
}

function catalog_record(string $type, int $id): ?array
{
    $tables = ['cemetery' => 'cemeteries', 'plot' => 'plots', 'service' => 'service_offerings'];
    if (!isset($tables[$type]) || $id < 1) return null;
    $query = db()->prepare('SELECT * FROM ' . $tables[$type] . ' WHERE id = ? LIMIT 1');
    $query->execute([$id]);
    return $query->fetch() ?: null;
}

function catalog_events(int $limit = 12): array
{
    $limit = max(1, min($limit, 50));
    return db()->query('SELECT e.*, u.full_name AS actor_name FROM admin_events e JOIN users u ON u.id = e.actor_user_id ORDER BY e.created_at DESC, e.id DESC LIMIT ' . $limit)->fetchAll();
}

function catalog_event(PDO $connection, int $actorId, string $entityType, int $entityId, string $action, string $summary): void
{
    $query = $connection->prepare('INSERT INTO admin_events (actor_user_id, entity_type, entity_id, action, summary) VALUES (?, ?, ?, ?, ?)');
    $query->execute([$actorId, $entityType, $entityId, $action, $summary]);
}

function catalog_save_cemetery(array $values, int $actorId): array
{
    $id = catalog_id($values['id'] ?? '');
    $name = trim($values['name'] ?? '');
    $city = trim($values['city'] ?? '');
    $province = trim($values['province'] ?? '');
    $address = trim($values['address'] ?? '');
    $status = $id ? ($values['status'] ?? 'active') : 'active';
    $errors = [];
    if (($values['id'] ?? '') !== '' && !$id) $errors['form'] = 'Choose a valid cemetery record.';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors['name'] = 'Enter a cemetery name (2–120 characters).';
    if (mb_strlen($city) < 2 || mb_strlen($city) > 120) $errors['city'] = 'Enter a city or municipality.';
    if (mb_strlen($province) < 2 || mb_strlen($province) > 120) $errors['province'] = 'Enter a province.';
    if (mb_strlen($address) > 255) $errors['address'] = 'Keep the address under 255 characters.';
    if (!in_array($status, ['active', 'inactive'], true)) $errors['status'] = 'Choose a valid status.';
    if ($errors) return ['errors' => $errors];

    $connection = db();
    try {
        $connection->beginTransaction();
        if ($id) {
            $exists = $connection->prepare('SELECT id FROM cemeteries WHERE id = ? FOR UPDATE');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                $connection->rollBack();
                return ['errors' => ['form' => 'Cemetery record not found.']];
            }
            $query = $connection->prepare('UPDATE cemeteries SET name = ?, city = ?, province = ?, address = ?, status = ?, updated_by = ? WHERE id = ?');
            $query->execute([$name, $city, $province, $address, $status, $actorId, $id]);
            $action = 'updated';
        } else {
            $query = $connection->prepare('INSERT INTO cemeteries (name, city, province, address, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$name, $city, $province, $address, $status, $actorId, $actorId]);
            $id = (int) $connection->lastInsertId();
            $action = 'created';
        }
        catalog_event($connection, $actorId, 'cemetery', $id, $action, "$name · $city, $province ($status)");
        $connection->commit();
        return ['id' => $id];
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if ($exception->getCode() === '23000') return ['errors' => ['name' => 'This cemetery already exists in that city and province.']];
        throw $exception;
    }
}

function catalog_save_plot(array $values, int $actorId): array
{
    $id = catalog_id($values['id'] ?? '');
    $cemeteryId = catalog_id($values['cemetery_id'] ?? '');
    $section = trim($values['section_code'] ?? '');
    $block = trim($values['block_code'] ?? '');
    $row = trim($values['row_code'] ?? '');
    $lot = trim($values['lot_code'] ?? '');
    $landmark = trim($values['landmark'] ?? '');
    $status = $id ? ($values['status'] ?? 'active') : 'active';
    $errors = [];
    if (($values['id'] ?? '') !== '' && !$id) $errors['form'] = 'Choose a valid plot reference.';
    if (!$cemeteryId || !catalog_record('cemetery', $cemeteryId)) $errors['cemetery_id'] = 'Choose a cemetery.';
    if (mb_strlen($section) < 1 || mb_strlen($section) > 80) $errors['section_code'] = 'Enter a section (up to 80 characters).';
    if (mb_strlen($block) > 80) $errors['block_code'] = 'Keep the block under 80 characters.';
    if (mb_strlen($row) > 80) $errors['row_code'] = 'Keep the row under 80 characters.';
    if (mb_strlen($lot) < 1 || mb_strlen($lot) > 80) $errors['lot_code'] = 'Enter a lot or grave number (up to 80 characters).';
    if (mb_strlen($landmark) > 255) $errors['landmark'] = 'Keep the landmark under 255 characters.';
    if (!in_array($status, ['active', 'inactive'], true)) $errors['status'] = 'Choose a valid status.';
    if ($errors) return ['errors' => $errors];

    $connection = db();
    try {
        $connection->beginTransaction();
        if ($id) {
            $exists = $connection->prepare('SELECT id FROM plots WHERE id = ? FOR UPDATE');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                $connection->rollBack();
                return ['errors' => ['form' => 'Plot reference not found.']];
            }
            $query = $connection->prepare('UPDATE plots SET cemetery_id = ?, section_code = ?, block_code = ?, row_code = ?, lot_code = ?, landmark = ?, status = ?, updated_by = ? WHERE id = ?');
            $query->execute([$cemeteryId, $section, $block, $row, $lot, $landmark, $status, $actorId, $id]);
            $action = 'updated';
        } else {
            $query = $connection->prepare('INSERT INTO plots (cemetery_id, section_code, block_code, row_code, lot_code, landmark, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$cemeteryId, $section, $block, $row, $lot, $landmark, $status, $actorId, $actorId]);
            $id = (int) $connection->lastInsertId();
            $action = 'created';
        }
        catalog_event($connection, $actorId, 'plot', $id, $action, "Section $section · Block " . ($block ?: '—') . " · Row " . ($row ?: '—') . " · Lot $lot");
        $connection->commit();
        return ['id' => $id];
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if ($exception->getCode() === '23000') return ['errors' => ['lot_code' => 'This cemetery already has that section, block, row, and lot reference.']];
        throw $exception;
    }
}

function catalog_save_service(array $values, int $actorId): array
{
    $id = catalog_id($values['id'] ?? '');
    $cemeteryId = catalog_id($values['cemetery_id'] ?? '');
    $name = trim($values['name'] ?? '');
    $description = trim($values['description'] ?? '');
    $price = catalog_price_centavos(trim($values['price'] ?? ''));
    $status = $id ? ($values['status'] ?? 'active') : 'active';
    $errors = [];
    if (($values['id'] ?? '') !== '' && !$id) $errors['form'] = 'Choose a valid service offering.';
    if (!$cemeteryId || !catalog_record('cemetery', $cemeteryId)) $errors['cemetery_id'] = 'Choose a cemetery.';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors['name'] = 'Enter a service name (2–120 characters).';
    if (mb_strlen($description) > 1000) $errors['description'] = 'Keep the description under 1,000 characters.';
    if ($price === null) $errors['price'] = 'Enter a price in pesos from ₱1.00 to ₱999,999.99.';
    if (!in_array($status, ['active', 'inactive'], true)) $errors['status'] = 'Choose a valid status.';
    if ($errors) return ['errors' => $errors];

    $connection = db();
    try {
        $connection->beginTransaction();
        $oldPrice = null;
        if ($id) {
            $existing = $connection->prepare('SELECT price_centavos FROM service_offerings WHERE id = ? FOR UPDATE');
            $existing->execute([$id]);
            $oldPrice = $existing->fetchColumn();
            if ($oldPrice === false) {
                $connection->rollBack();
                return ['errors' => ['form' => 'Service offering not found.']];
            }
            $query = $connection->prepare('UPDATE service_offerings SET cemetery_id = ?, name = ?, description = ?, price_centavos = ?, status = ?, updated_by = ? WHERE id = ?');
            $query->execute([$cemeteryId, $name, $description, $price, $status, $actorId, $id]);
            $action = 'updated';
        } else {
            $query = $connection->prepare('INSERT INTO service_offerings (cemetery_id, name, description, price_centavos, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $query->execute([$cemeteryId, $name, $description, $price, $status, $actorId, $actorId]);
            $id = (int) $connection->lastInsertId();
            $action = 'created';
        }
        if ($oldPrice === null || (int) $oldPrice !== $price) {
            $history = $connection->prepare('INSERT INTO service_price_history (service_id, price_centavos, changed_by) VALUES (?, ?, ?)');
            $history->execute([$id, $price, $actorId]);
        }
        catalog_event($connection, $actorId, 'service', $id, $action, "$name · " . catalog_money($price) . " ($status, pilot estimate)");
        $connection->commit();
        return ['id' => $id];
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if ($exception->getCode() === '23000') return ['errors' => ['name' => 'This service name already exists at that cemetery.']];
        throw $exception;
    }
}

function catalog_caretaker_access(): array
{
    return db()->query('SELECT a.caretaker_id, a.cemetery_id, c.name AS cemetery_name FROM caretaker_cemeteries a JOIN cemeteries c ON c.id = a.cemetery_id ORDER BY c.name')->fetchAll();
}

function catalog_change_access(int $caretakerId, int $cemeteryId, int $actorId, string $decision): array
{
    if ($caretakerId < 1 || $cemeteryId < 1 || !in_array($decision, ['grant', 'revoke'], true)) return ['errors' => ['form' => 'Choose a valid caretaker and cemetery.']];
    $connection = db();
    try {
        $connection->beginTransaction();
        $query = $connection->prepare("SELECT status FROM users WHERE id = ? AND role = 'caretaker' FOR UPDATE");
        $query->execute([$caretakerId]);
        $caretakerStatus = $query->fetchColumn();
        $query = $connection->prepare('SELECT name, status FROM cemeteries WHERE id = ? FOR UPDATE');
        $query->execute([$cemeteryId]);
        $cemetery = $query->fetch();
        if (!$caretakerStatus || !$cemetery || ($decision === 'grant' && ($caretakerStatus !== 'verified' || $cemetery['status'] !== 'active'))) {
            $connection->rollBack();
            return ['errors' => ['form' => 'Only verified caretakers can be authorized at an active cemetery.']];
        }
        if ($decision === 'grant') {
            $query = $connection->prepare('INSERT INTO caretaker_cemeteries (caretaker_id, cemetery_id, authorized_by) VALUES (?, ?, ?)');
            $query->execute([$caretakerId, $cemeteryId, $actorId]);
        } else {
            $query = $connection->prepare('DELETE FROM caretaker_cemeteries WHERE caretaker_id = ? AND cemetery_id = ?');
            $query->execute([$caretakerId, $cemeteryId]);
            if ($query->rowCount() === 0) {
                $connection->rollBack();
                return ['errors' => ['form' => 'That caretaker has no access to this cemetery.']];
            }
        }
        catalog_event($connection, $actorId, 'caretaker_access', $caretakerId, $decision === 'grant' ? 'authorized' : 'revoked', $cemetery['name'] . ' · caretaker #' . $caretakerId);
        $connection->commit();
        return ['id' => $caretakerId];
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if ($exception->getCode() === '23000') return ['errors' => ['form' => 'This caretaker is already authorized for that cemetery.']];
        throw $exception;
    }
}
