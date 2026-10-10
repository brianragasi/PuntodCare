<!doctype html>
<html lang="en" data-theme="puntod">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0b1f4b"><title><?= e($errorTitle) ?> · Puntod Care</title><link rel="icon" href="<?= e(asset_url('brand-icon.png')) ?>" type="image/png"><link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>"></head>
<body class="error-page"><main class="error-card"><span class="error-brand-icon"><img src="<?= e(asset_url('brand-icon.png')) ?>" width="1024" height="1024" alt=""></span><p class="eyebrow">PUNTOD CARE</p><h1><?= e($errorTitle) ?></h1><p><?= e($errorMessage) ?></p><a class="btn btn-primary" href="?page=overview">Return to Puntod Care</a></main></body>
</html>
