<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers.php';

$config = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($config['timezone']);

$role = isset($_GET['role']) && is_string($_GET['role']) && in_array($_GET['role'], $config['roles'], true)
    ? $_GET['role']
    : 'admin';
$page = isset($_GET['page']) && $_GET['page'] === 'components' ? 'components' : 'overview';
$roleLabels = ['admin' => 'Administrator', 'family' => 'Family member', 'caretaker' => 'Caretaker'];
$roleDescriptions = [
    'admin' => 'The place to oversee care and keep the pilot organized.',
    'family' => 'Keep close to the people and places that matter.',
    'caretaker' => 'A clear view of the care you are entrusted to give.',
];

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

require dirname(__DIR__) . '/app/views/layout.php';
