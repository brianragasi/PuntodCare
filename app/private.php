<?php
declare(strict_types=1);

function puntod_external_private_root(): ?string
{
    $path = dirname(__DIR__, 2) . '/puntodcare-private';
    return is_dir($path) ? $path : null;
}

function puntod_config_path(): string
{
    $privateRoot = puntod_external_private_root();
    return $privateRoot !== null ? $privateRoot . '/local.php' : dirname(__DIR__) . '/config/local.php';
}

function puntod_storage_directory(string $name): string
{
    if (!in_array($name, ['grave-photos', 'request-evidence'], true)) throw new InvalidArgumentException('Unknown private storage directory.');
    $privateRoot = puntod_external_private_root();
    return ($privateRoot !== null ? $privateRoot : dirname(__DIR__) . '/storage') . '/' . $name;
}
