<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/security.php';
require_once dirname(__DIR__) . '/app/auth.php';

$config = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($config['timezone']);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

start_secure_session();
$page = $_GET['page'] ?? 'overview';
if (!is_string($page) || !in_array($page, ['overview', 'components', 'account', 'caretakers', 'login', 'register', 'enroll'], true)) {
    http_response_code(404);
    $errorTitle = 'Page not found';
    $errorMessage = 'The page you requested does not exist.';
    require dirname(__DIR__) . '/app/views/error.php';
    exit;
}

try {
    db()->query('SELECT 1 FROM users LIMIT 1');
    $user = current_user();
} catch (Throwable $exception) {
    error_log((string) $exception);
    http_response_code(503);
    $errorTitle = 'Puntod Care is being set up';
    $errorMessage = 'The database is unavailable. Check the local configuration and run the accounts migration.';
    require dirname(__DIR__) . '/app/views/error.php';
    exit;
}

$errors = [];
$old = [];
$authError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        http_response_code(403);
        $errorTitle = 'Your session expired';
        $errorMessage = 'Refresh the page and try again.';
        require dirname(__DIR__) . '/app/views/error.php';
        exit;
    }
    $action = request_value('action');

    if ($action === 'logout') {
        if ($user) logout_user();
        redirect_to('login');
    }

    if ($action === 'login' && !$user) {
        $page = 'login';
        $old['email'] = request_value('email');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (attempt_login($old['email'], request_raw_string('password'), $ip)) {
            set_flash('Welcome back.');
            redirect_to();
        }
        $authError = 'We could not sign you in. Check your details or try again later.';
    } elseif (in_array($action, ['register_family', 'enroll_caretaker'], true) && !$user) {
        $roleToCreate = $action === 'enroll_caretaker' ? 'caretaker' : 'family';
        $page = $roleToCreate === 'caretaker' ? 'enroll' : 'register';
        $old = [
            'full_name' => request_value('full_name'),
            'email' => request_value('email'),
            'phone' => request_value('phone'),
            'service_area' => request_value('service_area'),
            'experience' => request_value('experience'),
        ];
        $result = register_account($roleToCreate, $old + [
            'password' => request_raw_string('password'),
            'password_confirmation' => request_raw_string('password_confirmation'),
        ]);
        if (isset($result['id'])) {
            login_user((int) $result['id']);
            set_flash($roleToCreate === 'caretaker' ? 'Application received. An administrator will review it.' : 'Your family account is ready.');
            redirect_to();
        }
        $errors = $result['errors'];
    } elseif ($action === 'review_caretaker' && $user && $user['role'] === 'admin') {
        $subjectId = request_value('subject_id');
        $decision = request_value('decision');
        $note = request_value('note');
        $ok = ctype_digit($subjectId) && review_caretaker((int) $subjectId, (int) $user['id'], $decision, $note);
        set_flash($ok ? 'Caretaker status updated.' : 'The review could not be completed. Check the status and required reason.', $ok ? 'success' : 'error');
        redirect_to('caretakers');
    } else {
        http_response_code(403);
        $errorTitle = 'Action unavailable';
        $errorMessage = 'You do not have permission to perform this action.';
        require dirname(__DIR__) . '/app/views/error.php';
        exit;
    }
}

if (!$user) {
    if (!in_array($page, ['login', 'register', 'enroll'], true)) redirect_to('login');
    require dirname(__DIR__) . '/app/views/auth.php';
    exit;
}
if (in_array($page, ['login', 'register', 'enroll'], true)) redirect_to();
if ($page === 'caretakers' && $user['role'] !== 'admin') {
    http_response_code(403);
    $errorTitle = 'Access denied';
    $errorMessage = 'Only platform administrators can review caretaker applications.';
    require dirname(__DIR__) . '/app/views/error.php';
    exit;
}

$role = $user['role'];
$roleLabels = ['admin' => 'Administrator', 'family' => 'Family member', 'caretaker' => 'Caretaker'];
$flash = take_flash();
if ($page === 'caretakers') $caretakers = list_caretakers();
if ($page === 'overview' && $role === 'admin') {
    $accountStats = ['families' => 0, 'pending' => 0, 'verified' => 0, 'total' => 0];
    foreach (db()->query('SELECT role, status, COUNT(*) AS total FROM users GROUP BY role, status')->fetchAll() as $group) {
        $count = (int) $group['total'];
        $accountStats['total'] += $count;
        if ($group['role'] === 'family') $accountStats['families'] += $count;
        if ($group['role'] === 'caretaker' && $group['status'] === 'pending') $accountStats['pending'] += $count;
        if ($group['role'] === 'caretaker' && $group['status'] === 'verified') $accountStats['verified'] += $count;
    }
}
if ($page === 'account' && $role === 'caretaker') {
    $statement = db()->prepare('SELECT service_area, experience, review_note, reviewed_at FROM caretaker_applications WHERE user_id = ?');
    $statement->execute([$user['id']]);
    $application = $statement->fetch();
}

require dirname(__DIR__) . '/app/views/layout.php';
