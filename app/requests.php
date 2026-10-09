<?php
declare(strict_types=1);

function request_status_label(string $status): string
{
    return [
        'requested' => 'Requested', 'assigned' => 'Assigned', 'accepted' => 'Accepted',
        'in_progress' => 'In progress', 'awaiting_review' => 'Awaiting family review',
        'completed' => 'Completed', 'issue_reported' => 'Issue reported', 'cancelled' => 'Cancelled',
    ][$status] ?? 'Unknown';
}

function request_status_tone(string $status): string
{
    return match ($status) {
        'completed' => 'success', 'issue_reported' => 'error', 'cancelled' => 'neutral',
        'requested', 'awaiting_review' => 'info', default => 'warning',
    };
}

function request_available_services(int $cemeteryId): array
{
    $query = db()->prepare("SELECT s.id, s.name, s.description, s.price_centavos FROM service_offerings s JOIN cemeteries c ON c.id = s.cemetery_id WHERE s.cemetery_id = ? AND s.status = 'active' AND c.status = 'active' ORDER BY s.name");
    $query->execute([$cemeteryId]);
    return $query->fetchAll();
}

function request_candidates(int $cemeteryId): array
{
    $query = db()->prepare("SELECT u.id, u.full_name FROM users u JOIN caretaker_cemeteries a ON a.caretaker_id = u.id WHERE a.cemetery_id = ? AND u.role = 'caretaker' AND u.status = 'verified' ORDER BY u.full_name");
    $query->execute([$cemeteryId]);
    return $query->fetchAll();
}

function request_list(array $user): array
{
    $role = $user['role'];
    if ($role === 'caretaker' && $user['status'] !== 'verified') return [];
    $where = $role === 'family' ? 'r.family_user_id = ?' : ($role === 'caretaker' ? 'r.caretaker_id = ? AND EXISTS (SELECT 1 FROM caretaker_cemeteries a WHERE a.caretaker_id = r.caretaker_id AND a.cemetery_id = r.cemetery_id)' : '1 = 1');
    $query = db()->prepare("SELECT r.id, r.grave_id, r.service_name, r.price_centavos, r.deceased_name, r.cemetery_name, r.preferred_date, r.status, r.created_at, f.full_name AS family_name, c.full_name AS caretaker_name FROM service_requests r JOIN users f ON f.id = r.family_user_id LEFT JOIN users c ON c.id = r.caretaker_id WHERE $where ORDER BY FIELD(r.status, 'issue_reported', 'requested', 'assigned', 'accepted', 'in_progress', 'awaiting_review', 'completed', 'cancelled'), r.created_at DESC, r.id DESC LIMIT 100");
    $query->execute($role === 'admin' ? [] : [(int) $user['id']]);
    return $query->fetchAll();
}

function request_for_grave(int $graveId, int $familyId): array
{
    $query = db()->prepare('SELECT id, service_name, status, created_at FROM service_requests WHERE grave_id = ? AND family_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
    $query->execute([$graveId, $familyId]);
    return $query->fetchAll();
}

function request_find(int $id, array $user): ?array
{
    if ($id < 1) return null;
    $query = db()->prepare('SELECT r.*, f.full_name AS family_name, c.full_name AS caretaker_name FROM service_requests r JOIN users f ON f.id = r.family_user_id LEFT JOIN users c ON c.id = r.caretaker_id WHERE r.id = ? LIMIT 1');
    $query->execute([$id]);
    $request = $query->fetch();
    if (!$request) return null;
    if ($user['role'] === 'family' && (int) $request['family_user_id'] !== (int) $user['id']) return null;
    if ($user['role'] === 'caretaker') {
        if ($user['status'] !== 'verified' || (int) $request['caretaker_id'] !== (int) $user['id']) return null;
        $access = db()->prepare('SELECT 1 FROM caretaker_cemeteries WHERE caretaker_id = ? AND cemetery_id = ? LIMIT 1');
        $access->execute([$user['id'], $request['cemetery_id']]);
        if (!$access->fetchColumn()) return null;
    }
    return $request;
}

function request_events(int $id): array
{
    $query = db()->prepare('SELECT e.*, u.full_name AS actor_name FROM request_events e JOIN users u ON u.id = e.actor_user_id WHERE e.request_id = ? ORDER BY e.created_at ASC, e.id ASC');
    $query->execute([$id]);
    return $query->fetchAll();
}

function request_event(PDO $connection, int $id, int $actorId, ?string $from, string $to, string $note = ''): void
{
    $query = $connection->prepare('INSERT INTO request_events (request_id, actor_user_id, from_status, to_status, note) VALUES (?, ?, ?, ?, ?)');
    $query->execute([$id, $actorId, $from, $to, $note]);
}

function request_create(array $values, array $user): array
{
    if ($user['role'] !== 'family') return ['not_found' => true];
    $graveId = catalog_id($values['grave_id'] ?? '');
    $grave = grave_find($graveId, (int) $user['id']);
    if (!$grave) return ['not_found' => true];
    $serviceChoice = $values['service_id'] ?? '';
    $validChoice = preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $serviceChoice, $choiceParts) === 1;
    $serviceId = $validChoice ? catalog_id($choiceParts[1]) : 0;
    $quotedPrice = $validChoice ? (int) $choiceParts[2] : 0;
    $date = trim($values['preferred_date'] ?? '');
    $instructions = trim($values['instructions'] ?? '');
    $errors = [];
    if (!$serviceId) $errors['service_id'] = 'Choose an available care service.';
    $preferred = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
    if (!$preferred || $preferred->format('Y-m-d') !== $date) $errors['preferred_date'] = 'Choose a valid preferred date.';
    if (!isset($errors['preferred_date']) && ($date < date('Y-m-d') || $date > date('Y-m-d', strtotime('+1 year')))) $errors['preferred_date'] = 'Choose a date from today through the next year.';
    if (mb_strlen($instructions) > 1000) $errors['instructions'] = 'Keep instructions under 1,000 characters.';
    if (($values['price_ack'] ?? '') !== '1') $errors['price_ack'] = 'Review the pilot estimate before requesting care.';
    if ($errors) return ['errors' => $errors];

    $connection = db();
    $connection->beginTransaction();
    try {
        $graveQuery = $connection->prepare('SELECT g.*, c.name AS cemetery_name, c.status AS cemetery_status FROM grave_profiles g JOIN cemeteries c ON c.id = g.cemetery_id WHERE g.id = ? AND g.family_user_id = ? FOR UPDATE');
        $graveQuery->execute([$graveId, $user['id']]);
        $grave = $graveQuery->fetch();
        if (!$grave) {
            $connection->rollBack();
            return ['not_found' => true];
        }
        $serviceQuery = $connection->prepare('SELECT * FROM service_offerings WHERE id = ? AND cemetery_id = ? AND status = ? FOR UPDATE');
        $serviceQuery->execute([$serviceId, $grave['cemetery_id'], 'active']);
        $service = $serviceQuery->fetch();
        if (!$service || $grave['cemetery_status'] !== 'active') {
            $connection->rollBack();
            return ['errors' => ['service_id' => 'This service is no longer available for the grave’s cemetery.']];
        }
        if ((int) $service['price_centavos'] !== $quotedPrice) {
            $connection->rollBack();
            return ['errors' => ['service_id' => 'The pilot price changed. Refresh this page and review the new price.']];
        }
        $open = $connection->prepare("SELECT id FROM service_requests WHERE grave_id = ? AND service_id = ? AND status IN ('requested', 'assigned', 'accepted', 'in_progress', 'awaiting_review', 'issue_reported') LIMIT 1");
        $open->execute([$graveId, $serviceId]);
        if ($open->fetchColumn()) {
            $connection->rollBack();
            return ['errors' => ['service_id' => 'An open request already exists for this service and grave. Check Care requests before submitting another.']];
        }
        $insert = $connection->prepare('INSERT INTO service_requests (grave_id, family_user_id, cemetery_id, service_id, service_name, service_description, price_centavos, deceased_name, headstone_name, cemetery_name, section_code, block_code, row_code, lot_code, preferred_date, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$graveId, $user['id'], $grave['cemetery_id'], $serviceId, $service['name'], $service['description'], $service['price_centavos'], $grave['deceased_name'], $grave['headstone_name'], $grave['cemetery_name'], $grave['section_code'], $grave['block_code'], $grave['row_code'], $grave['lot_code'], $date, $instructions]);
        $id = (int) $connection->lastInsertId();
        request_event($connection, $id, (int) $user['id'], null, 'requested', 'Family requested ' . $service['name'] . ' at ' . catalog_money((int) $service['price_centavos']) . ' (pilot estimate).');
        update_notify_admins($connection, $id, (int) $user['id'], 'New care request #' . $id . ' needs assignment.');
        $connection->commit();
        return ['id' => $id];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function request_transition(int $id, array $user, string $action, array $values): array
{
    if ($id < 1) return ['not_found' => true];
    $connection = db();
    $connection->beginTransaction();
    try {
        $query = $connection->prepare('SELECT * FROM service_requests WHERE id = ? FOR UPDATE');
        $query->execute([$id]);
        $request = $query->fetch();
        if (!$request || ($user['role'] === 'family' && (int) $request['family_user_id'] !== (int) $user['id']) || ($user['role'] === 'caretaker' && (int) $request['caretaker_id'] !== (int) $user['id'])) {
            $connection->rollBack();
            return ['not_found' => true];
        }
        $from = $request['status'];
        $rules = [
            'assign' => ['admin', ['requested'], 'assigned'],
            'requeue' => ['admin', ['assigned', 'accepted', 'in_progress'], 'requested'],
            'accept' => ['caretaker', ['assigned'], 'accepted'],
            'decline' => ['caretaker', ['assigned'], 'requested'],
            'start' => ['caretaker', ['accepted'], 'in_progress'],
            'submit' => ['caretaker', ['in_progress'], 'awaiting_review'],
            'approve' => ['family', ['awaiting_review'], 'completed'],
            'report_issue' => ['family', ['awaiting_review'], 'issue_reported'],
            'rework' => ['admin', ['issue_reported'], 'requested'],
            'resolve_issue' => ['admin', ['issue_reported'], 'awaiting_review'],
            'cancel' => ['family', ['requested', 'assigned', 'accepted'], 'cancelled'],
        ];
        $rule = $rules[$action] ?? null;
        if (!$rule || $user['role'] !== $rule[0] || !in_array($from, $rule[1], true)) {
            $connection->rollBack();
            return ['error' => 'This action is not available for the request’s current status.'];
        }
        $to = $rule[2];
        $note = trim($values['note'] ?? '');
        if (mb_strlen($note) > 1000 || (in_array($action, ['decline', 'requeue', 'submit', 'report_issue', 'rework', 'resolve_issue'], true) && mb_strlen($note) < 10)) {
            $connection->rollBack();
            return ['error' => 'Enter a reason or work note between 10 and 1,000 characters.'];
        }
        if ($user['role'] === 'caretaker') {
            $access = $connection->prepare("SELECT 1 FROM users u JOIN caretaker_cemeteries a ON a.caretaker_id = u.id WHERE u.id = ? AND u.role = 'caretaker' AND u.status = 'verified' AND a.cemetery_id = ? LIMIT 1 FOR UPDATE");
            $access->execute([$user['id'], $request['cemetery_id']]);
            if (!$access->fetchColumn()) {
                $connection->rollBack();
                return ['error' => 'Your verification or cemetery access has changed. Contact an administrator.'];
            }
        }
        $caretakerId = $request['caretaker_id'];
        if ($action === 'assign') {
            $candidateId = catalog_id($values['caretaker_id'] ?? '');
            $candidate = $connection->prepare("SELECT u.full_name FROM users u JOIN caretaker_cemeteries a ON a.caretaker_id = u.id JOIN cemeteries c ON c.id = a.cemetery_id WHERE u.id = ? AND u.role = 'caretaker' AND u.status = 'verified' AND a.cemetery_id = ? AND c.status = 'active' LIMIT 1 FOR UPDATE");
            $candidate->execute([$candidateId, $request['cemetery_id']]);
            $name = $candidate->fetchColumn();
            if (!$name) {
                $connection->rollBack();
                return ['error' => 'Choose a verified caretaker authorized for this active cemetery.'];
            }
            $caretakerId = $candidateId;
            $note = 'Assigned to ' . $name . '.';
        } elseif ($action === 'start') {
            $match = static fn (string $value): string => mb_strtolower(trim($value));
            if (($values['confirmed'] ?? '') !== '1' || $match($values['headstone_name'] ?? '') !== $match($request['headstone_name']) || $match($values['section_code'] ?? '') !== $match($request['section_code']) || $match($values['lot_code'] ?? '') !== $match($request['lot_code'])) {
                $connection->rollBack();
                return ['error' => 'Confirm the recorded headstone name, section, and lot before starting.'];
            }
            $note = 'Caretaker confirmed the headstone name, section, and lot before starting.';
        } elseif (in_array($action, ['decline', 'requeue', 'rework'], true)) {
            $caretakerId = null;
            if ($action === 'rework') $note = 'Administrator requested a new work round: ' . $note;
        } elseif ($action === 'accept') {
            $note = 'Caretaker accepted the assignment.';
        } elseif ($action === 'approve') {
            $note = 'Family approved the submitted report.';
        } elseif ($action === 'resolve_issue') {
            $note = 'Administrator response to the issue: ' . $note;
        }
        if (mb_strlen($note) > 1000) {
            $connection->rollBack();
            return ['error' => 'Keep the reason or work note under 1,000 characters.'];
        }
        if ($action === 'submit') {
            $round = request_round($connection, $id);
            $counts = $connection->prepare('SELECT stage, COUNT(*) AS total FROM request_evidence WHERE request_id = ? AND round_no = ? GROUP BY stage');
            $counts->execute([$id, $round]);
            $stages = ['before' => 0, 'after' => 0];
            foreach ($counts->fetchAll() as $entry) $stages[$entry['stage']] = (int) $entry['total'];
            if ($stages['before'] < 1 || $stages['after'] < 1) {
                $connection->rollBack();
                return ['error' => 'Add at least one before and one after photo for this work round before sending it for family review.'];
            }
        }
        $update = $connection->prepare('UPDATE service_requests SET status = ?, caretaker_id = ? WHERE id = ?');
        $update->execute([$to, $caretakerId, $id]);
        request_event($connection, $id, (int) $user['id'], $from, $to, $note);
        $actorId = (int) $user['id'];
        $familyId = (int) $request['family_user_id'];
        $assignedId = (int) ($request['caretaker_id'] ?? 0);
        if ($action === 'assign') {
            update_notify($connection, $id, $familyId, $actorId, 'Care request #' . $id . ' was assigned to a caretaker.');
            update_notify($connection, $id, (int) $caretakerId, $actorId, 'You have a new care assignment #' . $id . '.');
        } elseif ($action === 'submit') {
            update_notify($connection, $id, $familyId, $actorId, 'Before-and-after evidence for request #' . $id . ' is ready for review.');
            update_notify_admins($connection, $id, $actorId, 'Caretaker submitted evidence for request #' . $id . '.');
        } elseif ($action === 'report_issue') {
            update_notify($connection, $id, $assignedId, $actorId, 'The family reported an issue with request #' . $id . '.');
            update_notify_admins($connection, $id, $actorId, 'An issue with request #' . $id . ' needs review.');
        } elseif ($action === 'approve') {
            update_notify($connection, $id, $assignedId, $actorId, 'The family approved request #' . $id . '.');
            update_notify_admins($connection, $id, $actorId, 'Request #' . $id . ' was approved by the family.');
        } elseif ($action === 'decline') {
            update_notify($connection, $id, $familyId, $actorId, 'The caretaker declined request #' . $id . '; it needs reassignment.');
            update_notify_admins($connection, $id, $actorId, 'Caretaker declined request #' . $id . '; please reassign it.');
        } elseif (in_array($action, ['requeue', 'rework'], true)) {
            update_notify($connection, $id, $familyId, $actorId, 'Request #' . $id . ' was returned for assignment. See its timeline.');
        } elseif ($action === 'resolve_issue') {
            update_notify($connection, $id, $familyId, $actorId, 'Administrator responded to the issue on request #' . $id . '.');
            update_notify($connection, $id, $assignedId, $actorId, 'Administrator responded to the issue on request #' . $id . '.');
        } elseif ($action === 'cancel') {
            update_notify($connection, $id, $assignedId, $actorId, 'The family cancelled request #' . $id . '.');
            update_notify_admins($connection, $id, $actorId, 'The family cancelled request #' . $id . '.');
        } elseif (in_array($action, ['accept', 'start'], true)) {
            update_notify($connection, $id, $familyId, $actorId, 'Request #' . $id . ' is now ' . strtolower(request_status_label($to)) . '.');
        }
        $connection->commit();
        return ['id' => $id, 'status' => $to];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}
