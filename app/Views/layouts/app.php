<?php

declare(strict_types=1);

$pageTitle = isset($title) ? (string) $title : 'LegalOPS Cloud';
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
    <link rel="stylesheet" href="<?= e(asset('icons/font/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <?= $this->partial('navbar', ['currentUser' => $currentUser ?? null]) ?>
    <?= $this->partial('sidebar', ['section' => 'app', 'currentUser' => $currentUser ?? null]) ?>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-8"><h1 class="mb-0"><?= e($pageTitle) ?></h1></div>
                    <div class="col-sm-4 text-sm-end text-secondary small">Núcleo técnico</div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid"><?= $content ?></div>
        </div>
    </main>

    <?= $this->partial('footer') ?>
</div>
<script src="<?= e(asset('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('adminlte/js/adminlte.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/modules/saas.js')) ?>"></script>
<script src="<?= e(asset('js/modules/clientes.js')) ?>"></script>
<script src="<?= e(asset('js/modules/prospectos.js')) ?>"></script>
<script src="<?= e(asset('js/modules/casos.js')) ?>"></script>
<script src="<?= e(asset('js/modules/partes.js')) ?>"></script>
<script src="<?= e(asset('js/modules/timeline.js')) ?>"></script>
<script src="<?= e(asset('js/modules/terminos.js')) ?>"></script>
<script src="<?= e(asset('js/modules/audiencias.js')) ?>"></script>
<script src="<?= e(asset('js/modules/tareas.js')) ?>"></script>
<script src="<?= e(asset('js/modules/documentos.js')) ?>"></script>
<script src="<?= e(asset('js/modules/finanzas.js')) ?>"></script>
<script src="<?= e(asset('js/modules/notificaciones.js')) ?>"></script>
<script src="<?= e(asset('js/modules/dashboard.js')) ?>"></script>
<script src="<?= e(asset('js/modules/portal.js')) ?>"></script>
<script src="<?= e(asset('js/modules/reportes.js')) ?>"></script>
<script src="<?= e(asset('js/modules/importaciones.js')) ?>"></script>
<script src="<?= e(asset('js/modules/soporte.js')) ?>"></script>
<script src="<?= e(asset('js/modules/onboarding.js')) ?>"></script>
</body>
</html>
