<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/app/database.php';

$name = $argv[1] ?? '';
if (!is_string($name) || !preg_match('/^Puntod Test [A-Za-z0-9-]{4,40}$/', $name)) exit(1);

$connection = db();
$connection->beginTransaction();
try {
    $find = $connection->prepare('SELECT id FROM cemeteries WHERE name = ? FOR UPDATE');
    $find->execute([$name]);
    $cemeteryId = $find->fetchColumn();
    if (!$cemeteryId) {
        $connection->rollBack();
        echo "No temporary graves to remove.\n";
        exit(0);
    }
    $photos = $connection->prepare('SELECT p.storage_name FROM grave_photos p JOIN grave_profiles g ON g.id = p.grave_id WHERE g.cemetery_id = ?');
    $photos->execute([(int) $cemeteryId]);
    $storageNames = $photos->fetchAll(PDO::FETCH_COLUMN);
    $delete = $connection->prepare('DELETE FROM grave_profiles WHERE cemetery_id = ?');
    $delete->execute([(int) $cemeteryId]);
    $connection->commit();
    foreach ($storageNames as $storageName) {
        if (!preg_match('/^[a-f0-9]{48}$/', $storageName)) continue;
        $path = dirname(__DIR__) . '/storage/grave-photos/' . $storageName;
        if (is_file($path) && !unlink($path)) throw new RuntimeException('Could not remove a temporary photo.');
    }
    echo "Temporary graves and photos removed.\n";
} catch (Throwable $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    fwrite(STDERR, "Temporary grave cleanup failed.\n");
    error_log((string) $exception);
    exit(1);
}
