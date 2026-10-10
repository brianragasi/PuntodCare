<?php
$isLogin = $page === 'login';
$isCaretaker = $page === 'enroll';
$heading = $isLogin ? 'Welcome back.' : ($isCaretaker ? 'Care starts with you.' : 'Keep care close.');
$intro = $isLogin ? 'Sign in to your Puntod Care account.' : ($isCaretaker ? 'Apply to offer care at our pilot cemetery.' : 'Create a family account to begin caring from anywhere.');
?>
<!doctype html>
<html lang="en" data-theme="puntod">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0b1f4b"><title><?= e($heading) ?> · Puntod Care</title><link rel="icon" href="<?= e(asset_url('brand-icon.png')) ?>" type="image/png"><link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>"></head>
<body class="auth-page">
<a class="skip-link" href="#auth-main">Skip to form</a>
<div class="auth-shell">
    <aside class="auth-story"><a href="?page=login" class="auth-brand" aria-label="Puntod Care home"><picture><source media="(max-width: 900px)" srcset="<?= e(asset_url('brand-header.png')) ?>"><img src="<?= e(asset_url('brand-logo.png')) ?>" width="1600" height="1600" alt=""></picture></a><div class="auth-story-content"><span class="hero-kicker"><span class="hero-kicker-dot"></span> CARE FROM WHEREVER YOU ARE</span><h2>Love remembers.<br><em>Care continues.</em></h2><p>A thoughtful place for Filipino families and trusted local caretakers to care for a loved one's resting place.</p></div><span class="auth-story-footer">PUNTOD CARE · PILOT EXPERIENCE</span></aside>
    <main id="auth-main" class="auth-main"><div class="auth-card"><p class="eyebrow"><?= $isLogin ? 'YOUR ACCOUNT' : ($isCaretaker ? 'CARETAKER APPLICATION' : 'FAMILY REGISTRATION') ?></p><h1><?= e($heading) ?></h1><p class="auth-intro"><?= e($intro) ?></p>
        <?php if ($authError !== ''): ?><div class="alert auth-error" role="alert"><?= icon('close', 'size-4') ?><span><?= e($authError) ?></span></div><?php endif; ?>
        <?php if (!$isLogin && $isCaretaker): ?><div class="alert auth-info" role="note"><?= icon('shield', 'size-4') ?><span>Your application starts as pending. An administrator must verify you before you can receive work.</span></div><?php endif; ?>
        <form method="post" action="?page=<?= e($page) ?>" class="auth-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $isLogin ? 'login' : ($isCaretaker ? 'enroll_caretaker' : 'register_family') ?>">
            <?php if (!$isLogin): ?>
            <div class="form-field"><label for="full_name">Full name</label><input class="input input-bordered w-full" id="full_name" name="full_name" type="text" autocomplete="name" value="<?= e($old['full_name'] ?? '') ?>" required minlength="2" maxlength="120" aria-describedby="full_name_error"><small id="full_name_error" class="field-error"><?= e($errors['full_name'] ?? '') ?></small></div>
            <?php endif; ?>
            <div class="form-field"><label for="email">Email address</label><input class="input input-bordered w-full" id="email" name="email" type="email" autocomplete="email" value="<?= e($old['email'] ?? '') ?>" required maxlength="255" aria-describedby="email_error"><small id="email_error" class="field-error"><?= e($errors['email'] ?? '') ?></small></div>
            <?php if ($isCaretaker): ?>
            <div class="form-field"><label for="phone">Contact number</label><input class="input input-bordered w-full" id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>" required maxlength="30" aria-describedby="phone_error"><small id="phone_error" class="field-error"><?= e($errors['phone'] ?? '') ?></small></div>
            <div class="form-field"><label for="service_area">City or service area</label><input class="input input-bordered w-full" id="service_area" name="service_area" type="text" value="<?= e($old['service_area'] ?? '') ?>" required maxlength="160" placeholder="e.g. Cagayan de Oro City" aria-describedby="service_area_error"><small id="service_area_error" class="field-error"><?= e($errors['service_area'] ?? '') ?></small></div>
            <div class="form-field"><label for="experience">Relevant experience <span class="optional-label">Optional</span></label><textarea class="textarea textarea-bordered w-full" id="experience" name="experience" maxlength="1000" rows="3" aria-describedby="experience_error" placeholder="Tell us about the care services you can provide."><?= e($old['experience'] ?? '') ?></textarea><small id="experience_error" class="field-error"><?= e($errors['experience'] ?? '') ?></small></div>
            <?php endif; ?>
            <div class="form-field"><label for="password">Password</label><input class="input input-bordered w-full" id="password" name="password" type="password" autocomplete="<?= $isLogin ? 'current-password' : 'new-password' ?>" required <?= $isLogin ? '' : 'minlength="12" maxlength="72"' ?> aria-describedby="password_error"><small id="password_error" class="field-error"><?= e($errors['password'] ?? '') ?></small><?php if (!$isLogin): ?><small>Use at least 12 characters. Passwords are limited to 72 bytes.</small><?php endif; ?></div>
            <?php if (!$isLogin): ?><div class="form-field"><label for="password_confirmation">Confirm password</label><input class="input input-bordered w-full" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required aria-describedby="password_confirmation_error"><small id="password_confirmation_error" class="field-error"><?= e($errors['password_confirmation'] ?? '') ?></small></div><?php endif; ?>
            <button class="btn btn-primary auth-submit" type="submit"><?= $isLogin ? 'Sign in' : ($isCaretaker ? 'Submit application' : 'Create family account') ?> <?= icon('arrow', 'size-4') ?></button>
        </form>
        <div class="auth-links"><?php if ($isLogin): ?><p>New to Puntod Care? <a href="?page=register">Create a family account</a></p><p>Want to offer care? <a href="?page=enroll">Apply as a caretaker</a></p><?php else: ?><p>Already have an account? <a href="?page=login">Sign in</a></p><?php if (!$isCaretaker): ?><p>Are you a caretaker? <a href="?page=enroll">Apply here</a></p><?php endif; ?><?php endif; ?></div>
    </div></main>
</div>
<script src="<?= e(asset_url('app.js')) ?>" defer></script>
</body></html>
