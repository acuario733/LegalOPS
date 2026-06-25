<?php

declare(strict_types=1);

$pageTitle = isset($title) ? (string) $title : 'Portal cliente';
$csrf = isset($csrfToken) ? (string) $csrfToken : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($pageTitle) ?> · Portal LegalOPS</title>
    <link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('icons/font/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="layout-top-nav bg-body-tertiary">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body shadow-sm">
        <div class="container"><a class="navbar-brand fw-semibold" href="/"><span class="legalops-brand-mark me-2">L</span>Portal LegalOPS</a></div>
    </nav>
    <main class="app-main"><div class="app-content py-4"><div class="container"><?= $content ?></div></div></main>
    <?= $this->partial('footer') ?>
</div>
<script src="<?= e(asset('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('adminlte/js/adminlte.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/modules/saas.js')) ?>"></script>
<script src="<?= e(asset('js/modules/portal.js')) ?>"></script>
</body>
</html>
