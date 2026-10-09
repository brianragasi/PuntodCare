<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/security.php';
require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/catalog.php';
require_once dirname(__DIR__) . '/app/graves.php';

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    http_response_code(500);
    $errorTitle = 'Something went wrong';
    $errorMessage = 'Please try again later.';
    require dirname(__DIR__) . '/app/views/error.php';
});

$config = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($config['timezone']);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');

start_secure_session();
$page = $_GET['page'] ?? 'overview';
if (!is_string($page) || !in_array($page, ['overview', 'components', 'account', 'caretakers', 'cemeteries', 'plots', 'services', 'families', 'graves', 'grave', 'grave-form', 'grave-photo', 'login', 'register', 'enroll'], true)) {
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
$catalogErrors = [];
$catalogValues = [];
$graveErrors = [];
$graveValues = [];

if ($page === 'grave-photo') {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit;
    }
    $photoId = catalog_id(isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '');
    if (!$user || $user['role'] !== 'family' || !grave_send_photo($photoId, (int) $user['id'])) {
        http_response_code(404);
        $errorTitle = 'Photo not found';
        $errorMessage = 'The requested photo is unavailable.';
        require dirname(__DIR__) . '/app/views/error.php';
    }
    exit;
}

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
    } elseif (in_array($action, ['save_cemetery', 'save_plot', 'save_service', 'change_caretaker_access'], true) && $user && $user['role'] === 'admin') {
        if ($action === 'change_caretaker_access') {
            $result = catalog_change_access(catalog_id(request_value('caretaker_id')), catalog_id(request_value('cemetery_id')), (int) $user['id'], request_value('decision'));
            set_flash(isset($result['id']) ? 'Caretaker cemetery access updated.' : ($result['errors']['form'] ?? 'Access could not be updated.'), isset($result['id']) ? 'success' : 'error');
            redirect_to('caretakers');
        }
        $page = match ($action) {
            'save_cemetery' => 'cemeteries',
            'save_plot' => 'plots',
            default => 'services',
        };
        $fields = match ($action) {
            'save_cemetery' => ['id', 'name', 'city', 'province', 'address', 'status'],
            'save_plot' => ['id', 'cemetery_id', 'section_code', 'block_code', 'row_code', 'lot_code', 'landmark', 'status'],
            default => ['id', 'cemetery_id', 'name', 'description', 'price', 'status'],
        };
        foreach ($fields as $field) $catalogValues[$field] = request_value($field);
        $result = match ($action) {
            'save_cemetery' => catalog_save_cemetery($catalogValues, (int) $user['id']),
            'save_plot' => catalog_save_plot($catalogValues, (int) $user['id']),
            default => catalog_save_service($catalogValues, (int) $user['id']),
        };
        if (isset($result['id'])) {
            set_flash(match ($action) {
                'save_cemetery' => 'Cemetery record saved.',
                'save_plot' => 'Plot reference saved.',
                default => 'Service offering saved. Its price is a pilot estimate.',
            });
            redirect_to($page);
        }
        $catalogErrors = $result['errors'];
    } elseif ($action === 'save_grave' && $user && $user['role'] === 'family') {
        $page = 'grave-form';
        foreach (['id', 'cemetery_id', 'deceased_name', 'headstone_name', 'birth_date', 'death_date', 'section_code', 'block_code', 'row_code', 'lot_code', 'location_note', 'latitude', 'longitude'] as $field) $graveValues[$field] = request_value($field);
        $result = grave_save($graveValues, (int) $user['id']);
        if (isset($result['not_found'])) {
            http_response_code(404);
            $errorTitle = 'Grave not found';
            $errorMessage = 'This grave profile is unavailable.';
            require dirname(__DIR__) . '/app/views/error.php';
            exit;
        }
        if (isset($result['id'])) {
            set_flash('Grave profile saved.');
            redirect_to_grave($result['id']);
        }
        $graveErrors = $result['errors'];
    } elseif ($action === 'upload_grave_photo' && $user && $user['role'] === 'family') {
        $graveId = catalog_id(request_value('grave_id'));
        $result = grave_upload_photo($graveId, (int) $user['id'], $_FILES['photo'] ?? null, request_value('caption'));
        if (isset($result['not_found'])) {
            http_response_code(404);
            $errorTitle = 'Grave not found';
            $errorMessage = 'This grave profile is unavailable.';
            require dirname(__DIR__) . '/app/views/error.php';
            exit;
        }
        set_flash(isset($result['id']) ? 'Reference photo added.' : $result['error'], isset($result['id']) ? 'success' : 'error');
        redirect_to_grave($graveId);
    } elseif ($action === 'remove_grave_photo' && $user && $user['role'] === 'family') {
        $result = grave_remove_photo(catalog_id(request_value('photo_id')), (int) $user['id']);
        if (isset($result['not_found'])) {
            http_response_code(404);
            $errorTitle = 'Photo not found';
            $errorMessage = 'This reference photo is unavailable.';
            require dirname(__DIR__) . '/app/views/error.php';
            exit;
        }
        set_flash('Reference photo removed.');
        redirect_to_grave($result['grave_id']);
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
if (in_array($page, ['caretakers', 'cemeteries', 'plots', 'services', 'families'], true) && $user['role'] !== 'admin') {
    http_response_code(403);
    $errorTitle = 'Access denied';
    $errorMessage = 'Only platform administrators can manage pilot records.';
    require dirname(__DIR__) . '/app/views/error.php';
    exit;
}
if (in_array($page, ['graves', 'grave', 'grave-form', 'grave-photo'], true) && $user['role'] !== 'family') {
    http_response_code(403);
    $errorTitle = 'Access denied';
    $errorMessage = 'Only family members can manage their grave profiles.';
    require dirname(__DIR__) . '/app/views/error.php';
    exit;
}

$role = $user['role'];
$roleLabels = ['admin' => 'Administrator', 'family' => 'Family member', 'caretaker' => 'Caretaker'];
$flash = take_flash();
if ($page === 'caretakers') {
    $caretakers = list_caretakers();
    $cemeteries = catalog_cemeteries();
    $caretakerAccess = catalog_caretaker_access();
    $accessByCaretaker = [];
    foreach ($caretakerAccess as $access) $accessByCaretaker[(int) $access['caretaker_id']][] = $access;
}
if (in_array($page, ['cemeteries', 'plots', 'services'], true)) {
    $cemeteries = catalog_cemeteries();
    $editId = catalog_id(isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : '');
    $editType = match ($page) { 'cemeteries' => 'cemetery', 'plots' => 'plot', default => 'service' };
    $editRecord = $editId ? catalog_record($editType, $editId) : null;
    if ($editId && !$editRecord && !$catalogValues) {
        http_response_code(404);
        $errorTitle = 'Record not found';
        $errorMessage = 'The record you requested does not exist.';
        require dirname(__DIR__) . '/app/views/error.php';
        exit;
    }
    $filterCemeteryId = catalog_id(isset($_GET['cemetery_id']) && is_string($_GET['cemetery_id']) ? $_GET['cemetery_id'] : '');
    $catalogSearch = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';
    if ($page === 'plots') $plots = catalog_plots($filterCemeteryId, $catalogSearch);
    if ($page === 'services') $services = catalog_services($filterCemeteryId);
    if ($page === 'services' && $editRecord) $priceHistory = catalog_service_history($editId);
}
if ($page === 'families') {
    $catalogSearch = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';
    $families = catalog_families($catalogSearch);
}
if ($role === 'family' && in_array($page, ['overview', 'graves'], true)) $familyGraves = grave_list((int) $user['id']);
if ($page === 'grave' || $page === 'grave-form') {
    $rawRequestedId = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : ($graveValues['id'] ?? '');
    $requestedId = catalog_id($rawRequestedId);
    $grave = $requestedId ? grave_find($requestedId, (int) $user['id']) : null;
    if (($page === 'grave' || $rawRequestedId !== '') && !$grave) {
        http_response_code(404);
        $errorTitle = 'Grave not found';
        $errorMessage = 'This grave profile is unavailable.';
        require dirname(__DIR__) . '/app/views/error.php';
        exit;
    }
    if ($page === 'grave') {
        $gravePhotos = grave_photos((int) $grave['id']);
        $graveHistory = grave_history((int) $grave['id']);
    } else {
        $graveCemeteries = catalog_cemeteries();
    }
}
if ($page === 'overview' && $role === 'admin') {
    $accountStats = ['families' => 0, 'pending' => 0, 'verified' => 0, 'total' => 0];
    foreach (db()->query('SELECT role, status, COUNT(*) AS total FROM users GROUP BY role, status')->fetchAll() as $group) {
        $count = (int) $group['total'];
        $accountStats['total'] += $count;
        if ($group['role'] === 'family') $accountStats['families'] += $count;
        if ($group['role'] === 'caretaker' && $group['status'] === 'pending') $accountStats['pending'] += $count;
        if ($group['role'] === 'caretaker' && $group['status'] === 'verified') $accountStats['verified'] += $count;
    }
    $recentCatalogEvents = catalog_events(6);
    $catalogStats = [
        'cemeteries' => (int) db()->query("SELECT COUNT(*) FROM cemeteries WHERE status = 'active'")->fetchColumn(),
        'plots' => (int) db()->query("SELECT COUNT(*) FROM plots p JOIN cemeteries c ON c.id = p.cemetery_id WHERE p.status = 'active' AND c.status = 'active'")->fetchColumn(),
        'services' => (int) db()->query("SELECT COUNT(*) FROM service_offerings s JOIN cemeteries c ON c.id = s.cemetery_id WHERE s.status = 'active' AND c.status = 'active'")->fetchColumn(),
    ];
}
if ($page === 'account' && $role === 'caretaker') {
    $statement = db()->prepare('SELECT service_area, experience, review_note, reviewed_at FROM caretaker_applications WHERE user_id = ?');
    $statement->execute([$user['id']]);
    $application = $statement->fetch();
}

require dirname(__DIR__) . '/app/views/layout.php';
