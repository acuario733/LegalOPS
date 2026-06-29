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
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LegalOPS">
    <link rel="apple-touch-icon" href="/assets/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/assets/icons/icon-192.png">
    <meta name="msapplication-TileImage" content="/assets/icons/icon-144.png">
    <meta name="msapplication-TileColor" content="#1a1a2e">
    <link rel="stylesheet" href="<?= e(asset('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('icons/font/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
    <link rel="stylesheet" href="/assets/css/mobile.css">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div data-notification-container style="position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;"></div>
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
<script src="<?= e(asset('js/improvements.js')) ?>"></script>
<script src="<?= e(asset('js/Component.js')) ?>"></script>
<script src="<?= e(asset('js/FormComponent.js')) ?>"></script>
<script src="<?= e(asset('js/TableComponent.js')) ?>"></script>
<script src="<?= e(asset('js/ModalComponent.js')) ?>"></script>
<script src="<?= e(asset('js/init-components.js')) ?>"></script>
<script src="<?= e(asset('js/modules/saas.js')) ?>"></script>
<script src="<?= e(asset('js/modules/selects.js')) ?>"></script>
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
<script src="<?= e(asset('js/web-vitals.js')) ?>"></script>
<?php
// Inyectar BUILD_HASH para que web-vitals.js lo use al registrar el SW.
// El hash lo escribe webpack (BuildHashPlugin) en storage/build_hash.txt.
use App\Helpers\BuildHelper;
$swVersion = BuildHelper::buildHash();
?>
<script>window.SW_VERSION = '<?= e($swVersion) ?>';</script>
<script src="/assets/js/mobile-sidebar.js"></script>
<!-- PWA Install Prompt -->
<div id="pwa-install-banner"
     style="display:none;z-index:9999;"
     class="position-fixed bottom-0 start-0 end-0 p-3 bg-primary text-white
            d-flex align-items-center justify-content-between shadow-lg">
    <div>
        <strong>Instalar LegalOPS</strong>
        <small class="d-block">Accede más rápido desde tu pantalla de inicio</small>
    </div>
    <div class="d-flex gap-2">
        <button id="pwa-install-btn" class="btn btn-light btn-sm">Instalar</button>
        <button id="pwa-dismiss-btn" class="btn btn-outline-light btn-sm">Ahora no</button>
    </div>
</div>
<script src="/assets/js/pwa-install.js"></script>
</body>
</html>
