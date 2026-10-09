<?php
declare(strict_types=1);

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) || $id < 1) {
        return null;
    }
    $now = time();
    if (authenticated_session_expired($now)) {
        clear_authenticated_session();
        return null;
    }
    $statement = db()->prepare('SELECT id, full_name, email, phone, role, status, password_hash, created_at FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $user = $statement->fetch();
    if (!$user || $user['status'] === 'suspended' || !hash_equals(hash('sha256', $user['password_hash']), (string) ($_SESSION['password_fingerprint'] ?? ''))) {
        clear_authenticated_session();
        return null;
    }
    $_SESSION['last_activity_at'] = $now;
    unset($user['password_hash']);
    return $user;
}

function login_user(int $id): void
{
    $statement = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $passwordHash = $statement->fetchColumn();
    if (!is_string($passwordHash)) throw new RuntimeException('Account not found.');
    session_regenerate_id(true);
    $_SESSION = ['user_id' => $id, 'password_fingerprint' => hash('sha256', $passwordHash), 'csrf_token' => bin2hex(random_bytes(32)), 'authenticated_at' => time(), 'last_activity_at' => time()];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }
    session_destroy();
}

function register_account(string $role, array $values): array
{
    $errors = [];
    if (!in_array($role, ['family', 'caretaker'], true)) return ['errors' => ['form' => 'This account type cannot be registered.']];
    $name = trim($values['full_name'] ?? '');
    $email = strtolower(trim($values['email'] ?? ''));
    $phone = trim($values['phone'] ?? '');
    $password = $values['password'] ?? '';
    $confirmation = $values['password_confirmation'] ?? '';
    $serviceArea = trim($values['service_area'] ?? '');
    $experience = trim($values['experience'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors['full_name'] = 'Enter your full name (2–120 characters).';
    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if ($role === 'caretaker' && !preg_match('/^[0-9+() .-]{7,30}$/', $phone)) $errors['phone'] = 'Enter a contact number (7–30 characters).';
    if (strlen($password) < 12 || strlen($password) > 72) $errors['password'] = 'Use a password between 12 and 72 bytes.';
    if ($password !== $confirmation) $errors['password_confirmation'] = 'Passwords do not match.';
    if ($role === 'caretaker' && (mb_strlen($serviceArea) < 2 || mb_strlen($serviceArea) > 160)) $errors['service_area'] = 'Enter the city or area where you can work.';
    if ($role === 'caretaker' && mb_strlen($experience) > 1000) $errors['experience'] = 'Keep your experience summary under 1,000 characters.';
    if ($errors) return ['errors' => $errors];

    $connection = db();
    try {
        $connection->beginTransaction();
        $statement = $connection->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([$name, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT), $role, $role === 'caretaker' ? 'pending' : 'active']);
        $userId = (int) $connection->lastInsertId();
        if ($role === 'caretaker') {
            $application = $connection->prepare('INSERT INTO caretaker_applications (user_id, service_area, experience) VALUES (?, ?, ?)');
            $application->execute([$userId, $serviceArea, $experience !== '' ? $experience : null]);
        }
        $event = $connection->prepare('INSERT INTO account_events (subject_user_id, actor_user_id, action) VALUES (?, ?, ?)');
        $event->execute([$userId, $userId, 'registered']);
        $connection->commit();
        return ['id' => $userId];
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if ($exception->getCode() === '23000') return ['errors' => ['email' => 'An account with this email already exists.']];
        throw $exception;
    }
}

function login_attempt_limited(string $email, string $ip): bool
{
    $statement = db()->prepare('SELECT (SELECT COUNT(*) FROM login_attempts WHERE email_hash = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE) AS email_count, (SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE) AS ip_count');
    $statement->execute([hash('sha256', $email), hash('sha256', $ip)]);
    $counts = $statement->fetch();
    return (int) $counts['email_count'] >= 5 || (int) $counts['ip_count'] >= 20;
}

function record_login_failure(string $email, string $ip): void
{
    $statement = db()->prepare('INSERT INTO login_attempts (email_hash, ip_hash) VALUES (?, ?)');
    $statement->execute([hash('sha256', $email), hash('sha256', $ip)]);
}

function attempt_login(string $email, string $password, string $ip): bool
{
    $email = strtolower(trim($email));
    if (login_attempt_limited($email, $ip)) return false;
    $statement = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash']) || $user['status'] === 'suspended') {
        record_login_failure($email, $ip);
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    login_user((int) $user['id']);
    return true;
}

function list_caretakers(): array
{
    return db()->query("SELECT u.id, u.full_name, u.email, u.phone, u.status, u.created_at, a.service_area, a.experience, a.review_note, a.reviewed_at, reviewer.full_name AS reviewer_name FROM users u JOIN caretaker_applications a ON a.user_id = u.id LEFT JOIN users reviewer ON reviewer.id = a.reviewed_by WHERE u.role = 'caretaker' ORDER BY FIELD(u.status, 'pending', 'verified', 'rejected', 'suspended'), u.created_at DESC")->fetchAll();
}

function review_caretaker(int $subjectId, int $actorId, string $decision, string $note): bool
{
    $transitions = [
        'verify' => ['from' => ['pending', 'rejected'], 'to' => 'verified'],
        'reject' => ['from' => ['pending'], 'to' => 'rejected'],
        'suspend' => ['from' => ['verified'], 'to' => 'suspended'],
        'restore' => ['from' => ['suspended'], 'to' => 'verified'],
    ];
    if (!isset($transitions[$decision])) return false;
    if (in_array($decision, ['reject', 'suspend'], true) && $note === '') return false;
    if (mb_strlen($note) > 500) return false;
    $connection = db();
    $connection->beginTransaction();
    try {
        $statement = $connection->prepare("SELECT status FROM users WHERE id = ? AND role = 'caretaker' FOR UPDATE");
        $statement->execute([$subjectId]);
        $status = $statement->fetchColumn();
        if (!in_array($status, $transitions[$decision]['from'], true)) {
            $connection->rollBack();
            return false;
        }
        $update = $connection->prepare('UPDATE users SET status = ? WHERE id = ?');
        $update->execute([$transitions[$decision]['to'], $subjectId]);
        $application = $connection->prepare('UPDATE caretaker_applications SET review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ?');
        $application->execute([$note !== '' ? $note : null, $actorId, $subjectId]);
        $event = $connection->prepare('INSERT INTO account_events (subject_user_id, actor_user_id, action, note) VALUES (?, ?, ?, ?)');
        $event->execute([$subjectId, $actorId, $decision, $note !== '' ? $note : null]);
        $connection->commit();
        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}
