<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$outputDirectory = $root . '/storage';
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0700, true)) {
    fwrite(STDERR, "Cannot create the local output directory.\n");
    exit(1);
}

$migrations = glob($root . '/database/[0-9]*.sql');
if (!$migrations) {
    fwrite(STDERR, "No database migrations were found.\n");
    exit(1);
}
sort($migrations, SORT_STRING);

$sql = "-- Puntod Care schema. Select the empty hosted database in phpMyAdmin before importing.\n";
foreach ($migrations as $migration) {
    $contents = file_get_contents($migration);
    if ($contents === false) {
        fwrite(STDERR, "Cannot read " . basename($migration) . ".\n");
        exit(1);
    }
    $sql .= "\n-- " . basename($migration) . "\n" . rtrim($contents) . "\n";
}

$sqlPath = $outputDirectory . '/puntodcare-install.sql';
if (file_put_contents($sqlPath, $sql) === false) {
    fwrite(STDERR, "Cannot write the SQL import file.\n");
    exit(1);
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Created {$sqlPath}. Enable PHP's zip extension to also create the upload archive.\n");
    exit(1);
}

$zipPath = $outputDirectory . '/PuntodCare-GoogieHost.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create the upload archive.\n");
    exit(1);
}

$files = ['.htaccess', 'index.php', 'config/.htaccess', 'config/app.php'];
$trackedFiles = [];
exec('git -C ' . escapeshellarg($root) . ' ls-files -- app public', $trackedFiles, $gitExitCode);
if ($gitExitCode !== 0 || !$trackedFiles) {
    $zip->close();
    fwrite(STDERR, "Cannot list the tracked application files.\n");
    exit(1);
}
foreach ($trackedFiles as $relativePath) {
    if ($relativePath === 'public/assets/app.css.map') continue;
    if (!is_file($root . '/' . $relativePath)) {
        $zip->close();
        fwrite(STDERR, "A tracked application file is missing: {$relativePath}.\n");
        exit(1);
    }
    $files[] = $relativePath;
}
sort($files, SORT_STRING);

foreach ($files as $relativePath) {
    if (!$zip->addFile($root . '/' . $relativePath, $relativePath)) {
        $zip->close();
        fwrite(STDERR, "Cannot add {$relativePath} to the archive.\n");
        exit(1);
    }
}
if (!$zip->close()) {
    fwrite(STDERR, "Cannot finish the upload archive.\n");
    exit(1);
}

echo "Created {$zipPath} with " . count($files) . " application files.\n";
echo "Created {$sqlPath} from " . count($migrations) . " migrations.\n";
echo "The archive contains no database password, local config, or uploaded photos.\n";
