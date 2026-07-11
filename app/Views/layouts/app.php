<?php

declare(strict_types=1);

$pageTitle   = isset($title)     ? (string) $title     : 'LegalOPS Cloud';
$csrf        = isset($csrfToken) ? (string) $csrfToken : '';
$currentUser = $currentUser ?? null;

// Avatar: primeras 2 letras del nombre
$avatarText = '??';
if (is_array($currentUser) && !empty($currentUser['name'])) {
    $avatarText = strtoupper(mb_substr((string) $currentUser['name'], 0, 2));
}
$userName = is_array($currentUser) ? (string) ($currentUser['name'] ?? 'Usuario') : 'Usuario';
$userRole = is_array($currentUser) ? (string) ($currentUser['role'] ?? $currentUser['cargo'] ?? '') : '';
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
    <!-- Captura temprana del beforeinstallprompt antes de que los scripts se carguen -->
    <script>
    window.__pwaPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.__pwaPrompt = e;
        document.dispatchEvent(new Event('pwa-prompt-ready'));
    });
    </script>
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Design System (orden: base → componentes → overrides) -->
    <link rel="stylesheet" href="<?= e(asset('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('icons/font/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="/assets/css/topbar.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
    <link rel="stylesheet" href="/assets/css/mobile.css">
</head>
<body class="layout-fixed sidebar-expand-lg">
<div data-notification-container style="position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;pointer-events:none;"></div>

<div class="app-wrapper">
    <!-- SIDEBAR -->
    <aside class="app-sidebar" id="appSidebar" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="/dashboard" class="brand-link text-decoration-none d-flex align-items-center gap-2">
                <span class="legalops-brand-mark">L</span>
                <span class="brand-text">
                    LegalOPS Cloud
                    <span class="brand-badge-v2 d-block">V2</span>
                </span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav role="navigation" aria-label="Navegación principal">
                <?= $this->partial('sidebar', ['section' => 'app', 'currentUser' => $currentUser]) ?>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user-block">
                <div class="sidebar-avatar"><?= e($avatarText) ?></div>
                <div class="overflow-hidden">
                    <div class="sidebar-user-name"><?= e($userName) ?></div>
                    <div class="sidebar-user-role"><?= e($userRole) ?></div>
                </div>
            </div>
            <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Colapsar menú" type="button">
                <i class="bi bi-chevron-double-left" id="sidebarCollapseIcon"></i>
                <span>Colapsar</span>
            </button>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL — app-main-wrapper requerido por AdminLTE 4 CSS Grid -->
    <div class="app-main-wrapper" id="appMain">
        <!-- TOPBAR -->
        <header class="app-header" role="banner">
            <button class="mobile-menu-btn" id="mobileSidebarBtn"
                    aria-label="Abrir menú"
                    aria-expanded="false"
                    aria-controls="appSidebar"
                    type="button">
                <i class="bi bi-list"></i>
            </button>
            <span class="topbar-title"><?= e($pageTitle) ?></span>
            <div class="topbar-actions">
                <button class="topbar-icon-btn" aria-label="Notificaciones" type="button" id="topbarNotifBtn">
                    <i class="bi bi-bell"></i>
                    <span class="topbar-notif-badge" id="topbarNotifBadge" style="display:none;"></span>
                </button>
                <div class="dropdown">
                    <button class="topbar-avatar dropdown-toggle border-0" data-bs-toggle="dropdown"
                            aria-expanded="false" type="button" style="text-decoration:none;">
                        <?= e($avatarText) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text fw-semibold"><?= e($userName) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/mi-perfil"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                        <li>
                            <button type="button" class="dropdown-item" data-action="/logout" data-redirect="/login">
                                <i class="bi bi-box-arrow-right me-2"></i>Salir
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- CONTENIDO — cada vista renderiza su propio page-header si lo necesita -->
        <main class="app-main">
            <div class="app-content p-4">
                <?= $content ?>
            </div>
        </main>
    </div>
</div>

<!-- PWA Install Prompt -->
<div id="pwa-install-banner"
     style="display:none;z-index:9999;"
     class="position-fixed bottom-0 start-0 end-0 p-3 bg-primary text-white
            d-flex align-items:center justify-content-between shadow-lg">
    <div>
        <strong>Instalar LegalOPS</strong>
        <small class="d-block">Accede más rápido desde tu pantalla de inicio</small>
    </div>
    <div class="d-flex gap-2">
        <button id="pwa-install-btn" class="btn btn-light btn-sm">Instalar</button>
        <button id="pwa-dismiss-btn" class="btn btn-outline-light btn-sm">Ahora no</button>
    </div>
</div>

<!-- Scripts -->
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
use App\Helpers\BuildHelper;
$swVersion = BuildHelper::buildHash();
?>
<script>window.SW_VERSION = '<?= e($swVersion) ?>';</script>
<script src="/assets/js/sidebar.js"></script>
<script src="/assets/js/ui-components.js"></script>
<script src="/assets/js/pwa-install.js"></script>
</body>
</html>
