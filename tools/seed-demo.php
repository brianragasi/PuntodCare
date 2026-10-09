<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/database.php';
$app = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($app['timezone']);

$root = dirname(__DIR__);
$credentialsPath = $root . '/storage/demo-credentials.json';
$emails = [
    'admin' => 'pilot-admin@example.test',
    'family' => 'pilot-family@example.test',
    'caretaker' => 'pilot-caretaker@example.test',
];
$names = [
    'admin' => 'Pilot Demo Administrator',
    'family' => 'Pilot Demo Family',
    'caretaker' => 'Pilot Demo Caretaker',
];

try {
    $connection = db();
    $find = $connection->prepare('SELECT role, password_hash FROM users WHERE email = ?');
    $existing = [];
    foreach ($emails as $role => $email) {
        $find->execute([$email]);
        $existing[$role] = $find->fetch() ?: null;
    }
    if (is_file($credentialsPath)) {
        $saved = json_decode((string) file_get_contents($credentialsPath), true);
        $matches = is_array($saved);
        foreach ($emails as $role => $email) {
            $record = $existing[$role];
            $password = $saved['accounts'][$role]['password'] ?? null;
            if (!$record || $record['role'] !== $role || !is_string($password) || !password_verify($password, $record['password_hash'])) $matches = false;
        }
        $sampleCount = $connection->query("SELECT COUNT(*) FROM service_requests r JOIN cemeteries c ON c.id = r.cemetery_id WHERE c.name = 'Puntod DEMO Memorial Park' AND r.service_name IN ('DEMO Grave cleaning', 'DEMO Flower placement')")->fetchColumn();
        if ($matches && (int) $sampleCount === 2) {
            echo "Demo records already exist. Credentials: storage/demo-credentials.json\n";
            exit(0);
        }
    }
    if (is_file($credentialsPath) || array_filter($existing)) {
        throw new RuntimeException('Demo emails or credentials already exist in an incomplete setup. No records were changed.');
    }

    $credentials = ['label' => 'FICTIONAL LOCAL DEMO — NO PAYMENT', 'created_at' => date(DATE_ATOM), 'accounts' => []];
    $connection->beginTransaction();
    $insertUser = $connection->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $ids = [];
    foreach ($emails as $role => $email) {
        $password = bin2hex(random_bytes(18));
        $insertUser->execute([$names[$role], $email, $role === 'caretaker' ? '09170000000' : null, password_hash($password, PASSWORD_DEFAULT), $role, $role === 'caretaker' ? 'verified' : 'active']);
        $ids[$role] = (int) $connection->lastInsertId();
        $credentials['accounts'][$role] = ['email' => $email, 'password' => $password];
    }

    $insertApplication = $connection->prepare('INSERT INTO caretaker_applications (user_id, service_area, experience, review_note, reviewed_by, reviewed_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $insertApplication->execute([$ids['caretaker'], 'Cagayan de Oro City', 'Fictional demo caretaker for the local pilot.', 'Verified for fictional local demonstration only.', $ids['admin']]);
    $accountEvent = $connection->prepare('INSERT INTO account_events (subject_user_id, actor_user_id, action, note) VALUES (?, ?, ?, ?)');
    $accountEvent->execute([$ids['caretaker'], $ids['admin'], 'verified', 'Fictional local demo account.']);

    $cemetery = $connection->prepare('INSERT INTO cemeteries (name, city, province, address, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)');
    $cemetery->execute(['Puntod DEMO Memorial Park', 'Cagayan de Oro City', 'Misamis Oriental', 'Fictional location for demonstration only', $ids['admin'], $ids['admin']]);
    $cemeteryId = (int) $connection->lastInsertId();
    $plot = $connection->prepare('INSERT INTO plots (cemetery_id, section_code, block_code, row_code, lot_code, landmark, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $plot->execute([$cemeteryId, 'DEMO-A', 'B1', 'R1', '12', 'Fictional sample plot', $ids['admin'], $ids['admin']]);
    $access = $connection->prepare('INSERT INTO caretaker_cemeteries (caretaker_id, cemetery_id, authorized_by) VALUES (?, ?, ?)');
    $access->execute([$ids['caretaker'], $cemeteryId, $ids['admin']]);

    $grave = $connection->prepare('INSERT INTO grave_profiles (family_user_id, cemetery_id, deceased_name, headstone_name, section_code, block_code, row_code, lot_code, location_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $grave->execute([$ids['family'], $cemeteryId, 'Demo Memorial', 'Demo Memorial', 'DEMO-A', 'B1', 'R1', '12', 'Fictional grave for a product walkthrough.']);
    $graveId = (int) $connection->lastInsertId();
    $graveEvent = $connection->prepare('INSERT INTO grave_events (grave_id, actor_user_id, event_type, summary) VALUES (?, ?, ?, ?)');
    $graveEvent->execute([$graveId, $ids['family'], 'created', 'Fictional demo grave profile registered.']);

    $insertService = $connection->prepare('INSERT INTO service_offerings (cemetery_id, name, description, price_centavos, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)');
    $priceEvent = $connection->prepare('INSERT INTO service_price_history (service_id, price_centavos, changed_by) VALUES (?, ?, ?)');
    $insertRequest = $connection->prepare('INSERT INTO service_requests (grave_id, family_user_id, cemetery_id, service_id, service_name, service_description, price_centavos, deceased_name, headstone_name, cemetery_name, section_code, block_code, row_code, lot_code, preferred_date, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $requestEvent = $connection->prepare('INSERT INTO request_events (request_id, actor_user_id, from_status, to_status, note) VALUES (?, ?, NULL, ?, ?)');
    $update = $connection->prepare('INSERT INTO request_updates (recipient_user_id, request_id, message) VALUES (?, ?, ?)');
    $serviceRows = [
        ['DEMO Grave cleaning', 'Fictional care service for the approved-work walkthrough.', 50000],
        ['DEMO Flower placement', 'Fictional care service for the reported-issue walkthrough.', 35000],
    ];
    $preferredDate = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    foreach ($serviceRows as [$serviceName, $description, $price]) {
        $insertService->execute([$cemeteryId, $serviceName, $description, $price, $ids['admin'], $ids['admin']]);
        $serviceId = (int) $connection->lastInsertId();
        $priceEvent->execute([$serviceId, $price, $ids['admin']]);
        $insertRequest->execute([$graveId, $ids['family'], $cemeteryId, $serviceId, $serviceName, $description, $price, 'Demo Memorial', 'Demo Memorial', 'Puntod DEMO Memorial Park', 'DEMO-A', 'B1', 'R1', '12', $preferredDate, 'FICTIONAL DEMO ONLY. No visit or payment is scheduled.']);
        $requestId = (int) $connection->lastInsertId();
        $requestEvent->execute([$requestId, $ids['family'], 'requested', 'Fictional demo request at a pilot estimate; no payment collected.']);
        $update->execute([$ids['admin'], $requestId, 'DEMO request #' . $requestId . ' is ready for assignment.']);
    }

    $json = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!is_dir($root . '/storage') || file_put_contents($credentialsPath, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not save private demo credentials.');
    }
    @chmod($credentialsPath, 0600);
    $connection->commit();
    echo "Created three fictional demo accounts, one cemetery, one grave, and two ready-to-assign requests.\nCredentials: storage/demo-credentials.json\n";
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) {
        $connection->rollBack();
        if (is_file($credentialsPath)) unlink($credentialsPath);
    }
    fwrite(STDERR, "Demo setup failed: {$exception->getMessage()}\n");
    exit(1);
}
