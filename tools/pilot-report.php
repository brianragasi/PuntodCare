<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/app/database.php';

$cemeteryName = isset($argv[1]) ? trim($argv[1]) : 'Puntod DEMO Memorial Park';
if ($cemeteryName === '' || mb_strlen($cemeteryName) > 120) {
    fwrite(STDERR, "Usage: php tools/pilot-report.php [exact cemetery name]\n");
    exit(1);
}

try {
    $find = db()->prepare('SELECT id, name FROM cemeteries WHERE name = ? ORDER BY id');
    $find->execute([$cemeteryName]);
    $cemeteries = $find->fetchAll();
    if (count($cemeteries) !== 1) throw new RuntimeException('No unique cemetery matches that name. Supply an exact unique name.');
    $cemetery = $cemeteries[0];
    $id = (int) $cemetery['id'];

    $counts = db()->prepare('SELECT status, COUNT(*) AS total FROM service_requests WHERE cemetery_id = ? GROUP BY status');
    $counts->execute([$id]);
    $byStatus = [];
    foreach ($counts->fetchAll() as $row) $byStatus[$row['status']] = (int) $row['total'];

    $issues = db()->prepare("SELECT COUNT(*) FROM request_events e JOIN service_requests r ON r.id = e.request_id WHERE r.cemetery_id = ? AND e.to_status = 'issue_reported'");
    $issues->execute([$id]);
    $issueCount = (int) $issues->fetchColumn();

    $turnaround = db()->prepare("SELECT TIMESTAMPDIFF(MINUTE, r.created_at, MIN(e.created_at)) AS minutes_to_approval FROM service_requests r JOIN request_events e ON e.request_id = r.id AND e.to_status = 'completed' WHERE r.cemetery_id = ? GROUP BY r.id, r.created_at");
    $turnaround->execute([$id]);
    $durations = array_map('intval', $turnaround->fetchAll(PDO::FETCH_COLUMN));
    $total = array_sum($byStatus);
    $completed = $byStatus['completed'] ?? 0;
    $average = $durations ? number_format(array_sum($durations) / count($durations) / 60, 1) . ' hours' : 'n/a';

    echo "Pilot report: {$cemetery['name']}\n";
    echo "Requests: {$total}\nCompleted with family approval: {$completed}\nIssue reports raised: {$issueCount}\nAverage request-to-approval time: {$average}\n";
    foreach (['requested', 'assigned', 'accepted', 'in_progress', 'awaiting_review', 'issue_reported', 'completed', 'cancelled'] as $status) {
        echo ucfirst(str_replace('_', ' ', $status)) . ': ' . ($byStatus[$status] ?? 0) . "\n";
    }
    echo "Counts include fictional demo requests if this cemetery is a demo. No payment is recorded.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Pilot report failed: {$exception->getMessage()}\n");
    exit(1);
}
