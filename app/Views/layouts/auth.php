<?php

declare(strict_types=1);

$pageTitle = isset($title) ? (string) $title : 'Acceso';
$csrf = isset($csrfToken) ? (string) $csrfToken : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($pageTitle) ?> · LegalOPS Cloud</title>
    <link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="login-page bg-body-secondary">
<main class="login-box">
    <div class="text-center mb-4"><span class="legalops-brand-mark">L</span><h1 class="h4 mt-2">LegalOPS Cloud</h1></div>
    <?= $content ?>
</main>
<script src="<?= e(asset('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('adminlte/js/adminlte.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/modules/auth.js')) ?>"></script>
</body>
</html>
