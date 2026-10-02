<?php
declare(strict_types=1);

$isComponents = $page === 'components';
$overviewTitle = match ($role) {
    'family' => 'Family overview',
    'caretaker' => 'Your work',
    default => 'Overview',
};
$pageTitle = match ($page) {
    'components' => 'Interface kit',
    'account' => 'My account',
    'caretakers' => 'Caretaker review',
    default => $overviewTitle,
};
?>
<!doctype html>
<html lang="en" data-theme="puntod">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#183b33">
    <meta name="description" content="Puntod Care keeps families connected to the resting places of their loved ones.">
    <title><?= e($pageTitle) ?> · Puntod Care</title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body class="min-h-screen bg-base-200 text-base-content">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="app-shell">
    <div class="mobile-scrim" data-sidebar-close hidden></div>
    <aside class="sidebar" id="site-sidebar" aria-label="Main navigation">
        <div class="sidebar-brand">
            <a href="<?= e(app_url($role)) ?>" class="brand-link" aria-label="Puntod Care home">
                <span class="brand-mark"><?= icon('flower', 'size-6') ?></span>
                <span class="brand-type">puntod<span>care</span><small>Care that stays close</small></span>
            </a>
            <button class="btn btn-ghost btn-square lg:hidden" type="button" aria-label="Close navigation" data-sidebar-close-button><?= icon('close') ?></button>
        </div>

        <div class="sidebar-scroll">
            <div class="workspace-label">WORKSPACE <span class="workspace-dot"></span> PILOT</div>
            <div class="workspace-card">
                <span class="workspace-avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span>
                <span><strong><?= e($user['full_name']) ?></strong><small><?= e($roleLabels[$role]) ?></small></span>
            </div>

            <nav aria-label="Primary" class="sidebar-nav">
                <p class="nav-caption">YOUR SPACE</p>
                <a href="<?= e(app_url($role)) ?>" class="nav-link<?= $page === 'overview' ? ' is-active' : '' ?>" <?= $page === 'overview' ? 'aria-current="page"' : '' ?>><?= icon('grid') ?><span><?= e($overviewTitle) ?></span></a>
                <?php if ($role === 'admin'): ?><a href="?page=caretakers" class="nav-link<?= $page === 'caretakers' ? ' is-active' : '' ?>" <?= $page === 'caretakers' ? 'aria-current="page"' : '' ?>><?= icon('shield') ?><span>Caretaker review</span></a><?php endif; ?>
                <a href="?page=account" class="nav-link<?= $page === 'account' ? ' is-active' : '' ?>" <?= $page === 'account' ? 'aria-current="page"' : '' ?>><?= icon('users') ?><span>My account</span></a>
                <a href="<?= e(app_url($role, 'components')) ?>" class="nav-link<?= $isComponents ? ' is-active' : '' ?>" <?= $isComponents ? 'aria-current="page"' : '' ?>><?= icon('sparkle') ?><span>Interface kit</span></a>
                <p class="nav-caption nav-caption-spaced">COMING IN LATER PHASES</p>
                <?php if ($role === 'admin'): ?>
                    <span class="nav-link nav-pending"><?= icon('pin') ?><span>Cemetery records</span><small>Later</small></span>
                    <span class="nav-link nav-pending"><?= icon('document') ?><span>Service requests</span><small>Later</small></span>
                <?php elseif ($role === 'family'): ?>
                    <span class="nav-link nav-pending"><?= icon('heart') ?><span>My graves</span><small>Later</small></span>
                    <span class="nav-link nav-pending"><?= icon('calendar') ?><span>Care requests</span><small>Later</small></span>
                <?php else: ?>
                    <span class="nav-link nav-pending"><?= icon('document') ?><span>Assigned jobs</span><small>Later</small></span>
                    <span class="nav-link nav-pending"><?= icon('camera') ?><span>Photo reports</span><small>Later</small></span>
                <?php endif; ?>
            </nav>

            <form action="?page=overview" method="post" class="logout-form"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit" class="nav-link logout-button"><?= icon('arrow') ?><span>Sign out</span></button></form>

            <div class="sidebar-note">
                <div class="sidebar-note-icon"><?= icon('heart', 'size-4') ?></div>
                <p>Every resting place holds a story worth caring for.</p>
                <span>PUNTOD CARE · PILOT</span>
            </div>
        </div>
        <div class="sidebar-bottom"><span class="sidebar-bottom-dot"></span> Built with care in the Philippines</div>
    </aside>

    <div class="main-shell">
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-ghost btn-square mobile-menu lg:hidden" type="button" aria-label="Open navigation" aria-controls="site-sidebar" aria-expanded="false" data-sidebar-open><?= icon('menu') ?></button>
                <span class="breadcrumb-root">Puntod Care</span>
                <?= icon('chevron', 'size-3.5 breadcrumb-chevron') ?>
                <span class="breadcrumb-current"><?= e($pageTitle) ?></span>
            </div>
            <div class="topbar-right">
                <span class="role-chip"><?= e($roleLabels[$role]) ?></span>
                <span class="topbar-divider"></span>
                <span class="topbar-avatar" aria-hidden="true"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span>
            </div>
        </header>

        <main id="main-content" class="page-content" tabindex="-1">
            <?php if ($flash): ?><div class="alert account-flash <?= $flash['type'] === 'error' ? 'account-flash-error' : '' ?>" role="status"><?= icon($flash['type'] === 'error' ? 'close' : 'check', 'size-5') ?><span><?= e($flash['message']) ?></span></div><?php endif; ?>
            <?php if (in_array($page, ['overview', 'components'], true)): ?><div class="preview-banner" role="note"><?= icon('sparkle', 'size-4') ?><strong>Workspace preview</strong><span>Your account is real. Graves, requests, and dashboard figures are examples until later phases.</span></div><?php endif; ?>
            <?php if ($page === 'caretakers'): ?>
                <?php require __DIR__ . '/pages/caretakers.php'; ?>
            <?php elseif ($page === 'account'): ?>
                <?php require __DIR__ . '/pages/account.php'; ?>
            <?php elseif ($isComponents): ?>
                <?php require __DIR__ . '/pages/components.php'; ?>
            <?php else: ?>
                <?php require __DIR__ . '/pages/' . $role . '.php'; ?>
            <?php endif; ?>
        </main>
        <footer class="site-footer"><span>© <?= date('Y') ?> Puntod Care</span><span>Made for the moments that matter</span></footer>
    </div>
</div>
</body>
</html>
