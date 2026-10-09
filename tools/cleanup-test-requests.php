<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/app/database.php';

$name = $argv[1] ?? '';
if (!is_string($name) || !preg_match('/^Puntod Test [A-Za-z0-9-]{4,40}$/', $name)) exit(1);

$delete = db()->prepare('DELETE r FROM service_requests r JOIN cemeteries c ON c.id = r.cemetery_id WHERE c.name = ?');
$delete->execute([$name]);
echo "Temporary requests removed.\n";
