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
        echo "No temporary catalog record to remove.\n";
        exit(0);
    }
    $id = (int) $cemeteryId;
    foreach ([
        ["DELETE FROM admin_events WHERE entity_type = 'plot' AND entity_id IN (SELECT id FROM plots WHERE cemetery_id = ?)", [$id]],
        ["DELETE FROM admin_events WHERE entity_type = 'service' AND entity_id IN (SELECT id FROM service_offerings WHERE cemetery_id = ?)", [$id]],
        ["DELETE FROM admin_events WHERE entity_type = 'cemetery' AND entity_id = ?", [$id]],
        ["DELETE FROM admin_events WHERE entity_type = 'caretaker_access' AND summary LIKE ?", [$name . ' · %']],
        ['DELETE FROM caretaker_cemeteries WHERE cemetery_id = ?', [$id]],
        ['DELETE FROM service_price_history WHERE service_id IN (SELECT id FROM service_offerings WHERE cemetery_id = ?)', [$id]],
        ['DELETE FROM service_offerings WHERE cemetery_id = ?', [$id]],
        ['DELETE FROM plots WHERE cemetery_id = ?', [$id]],
        ['DELETE FROM cemeteries WHERE id = ?', [$id]],
    ] as [$sql, $params]) {
        $statement = $connection->prepare($sql);
        $statement->execute($params);
    }
    $connection->commit();
    echo "Temporary catalog records removed.\n";
} catch (Throwable $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    fwrite(STDERR, "Temporary catalog cleanup failed.\n");
    error_log((string) $exception);
    exit(1);
}
