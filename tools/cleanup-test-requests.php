<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/app/database.php';

$name = $argv[1] ?? '';
if (!is_string($name) || !preg_match('/^Puntod Test [A-Za-z0-9-]{4,40}$/', $name)) exit(1);

$photos = db()->prepare('SELECT e.storage_name FROM request_evidence e JOIN service_requests r ON r.id = e.request_id JOIN cemeteries c ON c.id = r.cemetery_id WHERE c.name = ?');
$photos->execute([$name]);
$storageNames = $photos->fetchAll(PDO::FETCH_COLUMN);
$delete = db()->prepare('DELETE r FROM service_requests r JOIN cemeteries c ON c.id = r.cemetery_id WHERE c.name = ?');
$delete->execute([$name]);
foreach ($storageNames as $storageName) {
    if (!preg_match('/^[a-f0-9]{48}$/', $storageName)) continue;
    $path = dirname(__DIR__) . '/storage/request-evidence/' . $storageName;
    if (is_file($path) && !unlink($path)) throw new RuntimeException('Could not remove temporary request evidence.');
}
echo "Temporary requests and evidence removed.\n";
