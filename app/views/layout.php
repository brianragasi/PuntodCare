<?php
declare(strict_types=1);

$isComponents = $page === 'components';
$pageTitle = $isComponents ? 'Interface kit' : match ($role) {
    'family' => 'Family overview',
    'caretaker' => 'Your work',
    default => 'Overview',
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
                <span class="workspace-avatar"><?= e(strtoupper(substr($role, 0, 1))) ?></span>
                <span><strong><?= e($roleLabels[$role]) ?></strong><small>Sample workspace</small></span>
            </div>

            <nav aria-label="Primary" class="sidebar-nav">
                <p class="nav-caption">YOUR SPACE</p>
                <a href="<?= e(app_url($role)) ?>" class="nav-link<?= $isComponents ? '' : ' is-active' ?>" <?= $isComponents ? '' : 'aria-current="page"' ?>><?= icon('grid') ?><span><?= e($pageTitle === 'Interface kit' ? ($role === 'admin' ? 'Overview' : ($role === 'family' ? 'Family overview' : 'Your work')) : $pageTitle) ?></span></a>
                <a href="<?= e(app_url($role, 'components')) ?>" class="nav-link<?= $isComponents ? ' is-active' : '' ?>" <?= $isComponents ? 'aria-current="page"' : '' ?>><?= icon('sparkle') ?><span>Interface kit</span></a>
                <p class="nav-caption nav-caption-spaced">COMING IN LATER PHASES</p>
                <?php if ($role === 'admin'): ?>
                    <span class="nav-link nav-pending"><?= icon('users') ?><span>People & providers</span><small>Later</small></span>
                    <span class="nav-link nav-pending"><?= icon('document') ?><span>Service requests</span><small>Later</small></span>
                <?php elseif ($role === 'family'): ?>
                    <span class="nav-link nav-pending"><?= icon('heart') ?><span>My graves</span><small>Later</small></span>
                    <span class="nav-link nav-pending"><?= icon('calendar') ?><span>Care requests</span><small>Later</small></span>
                <?php else: ?>
                    <span class="nav-link nav-pending"><?= icon('document') ?><span>Assigned jobs</span><small>Later</small></span>
                    <span class="nav-link nav-pending"><?= icon('camera') ?><span>Photo reports</span><small>Later</small></span>
                <?php endif; ?>
            </nav>

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
                <label for="role-preview" class="role-label">View as</label>
                <select id="role-preview" class="select select-sm role-select" aria-label="Preview workspace as" data-role-select>
                    <?php foreach ($roleLabels as $roleValue => $label): ?>
                    <option value="<?= e($roleValue) ?>" <?= $role === $roleValue ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="topbar-divider"></span>
                <span class="topbar-avatar" aria-hidden="true"><?= e(strtoupper(substr($role, 0, 1))) ?></span>
            </div>
        </header>

        <main id="main-content" class="page-content" tabindex="-1">
            <div class="preview-banner" role="note"><?= icon('sparkle', 'size-4') ?><strong>Phase 1 preview</strong><span>Sample content shows the interface. Accounts and live records come in the next phases.</span></div>
            <?php if ($isComponents): ?>
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
