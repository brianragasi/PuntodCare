<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $role, string $page = 'overview'): string
{
    // The role parameter remains for Phase 1 view compatibility. The server
    // derives the active role from the signed-in account, never from a URL.
    return '?page=' . rawurlencode($page);
}

function icon(string $name, string $class = 'size-5'): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'flower' => '<circle cx="12" cy="12" r="2"/><path d="M12 10c-3-2-2-7 0-7s3 5 0 7Zm2 2c2-3 7-2 7 0s-5 3-7 0Zm-2 2c3 2 2 7 0 7s-3-5 0-7Zm-2-2c-2 3-7 2-7 0s5-3 7 0Z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'camera' => '<path d="M5 7h3l2-3h4l2 3h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><circle cx="12" cy="13" r="3"/>',
        'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M5 5l14 14M19 5 5 19"/>',
        'sparkle' => '<path d="m12 2 2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2L12 2Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'document' => '<path d="M7 2h7l5 5v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h5M9 13h6M9 17h6"/>',
    ];

    $path = $paths[$name] ?? $paths['sparkle'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function status_badge(string $label, string $type = 'neutral'): string
{
    $classes = [
        'neutral' => 'badge badge-soft badge-neutral',
        'success' => 'badge badge-soft badge-success',
        'warning' => 'badge badge-soft badge-warning',
        'info' => 'badge badge-soft badge-info',
        'error' => 'badge badge-soft badge-error',
    ];
    return '<span class="' . ($classes[$type] ?? $classes['neutral']) . ' status-badge">' . e($label) . '</span>';
}
