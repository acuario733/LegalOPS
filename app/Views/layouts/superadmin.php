<?php

declare(strict_types=1);

$pageTitle   = isset($title)     ? (string) $title     : 'Superadministración';
$csrf        = isset($csrfToken) ? (string) $csrfToken  : '';
$currentUser = $currentUser ?? null;

$avatarText = '??';
if (is_array($currentUser) && !empty($currentUser['name'])) {
    $avatarText = strtoupper(mb_substr((string) $currentUser['name'], 0, 2));
}
$userName = is_array($currentUser) ? (string) ($currentUser['name']  ?? 'Superadmin') : 'Superadmin';
$userRole = is_array($currentUser) ? (string) ($currentUser['role']  ?? 'Superadmin') : 'Superadmin';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($pageTitle) ?> · Superadmin LegalOPS</title>
    <link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Design System -->
    <link rel="stylesheet" href="<?= e(asset('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('icons/font/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="/assets/css/design-system.css">
    <link rel="stylesheet" href="/assets/css/sidebar.css">
    <link rel="stylesheet" href="/assets/css/topbar.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="/assets/css/mobile.css">
    <style>
        /* Indicador visual de contexto SuperAdmin */
        .superadmin-context-bar { height: 3px; background: var(--color-danger); width: 100%; }
        .app-sidebar { border-top: 3px solid var(--color-danger) !important; }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg superadmin-mode">

<!-- Barra roja de 3px: contexto SuperAdmin -->
<div class="superadmin-context-bar" role="presentation" aria-hidden="true"></div>

<div data-notification-container style="position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;pointer-events:none;"></div>

<div class="app-wrapper">
    <!-- SIDEBAR SUPERADMIN -->
    <aside class="app-sidebar" id="appSidebar" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="/superadmin" class="brand-link text-decoration-none d-flex align-items-center gap-2">
                <span class="legalops-brand-mark">L</span>
                <span class="brand-text">
                    LegalOPS Cloud
                    <span class="brand-badge-superadmin d-block">SUPERADMIN</span>
                </span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav role="navigation" aria-label="Navegación SuperAdmin">
                <?= $this->partial('sidebar', ['section' => 'superadmin', 'currentUser' => $currentUser]) ?>
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
                <span class="badge" style="background:var(--color-danger);color:#fff;font-size:11px;padding:4px 10px;border-radius:var(--radius-full);">
                    SUPERADMIN
                </span>
                <div class="dropdown">
                    <button class="topbar-avatar dropdown-toggle border-0" data-bs-toggle="dropdown"
                            aria-expanded="false" type="button">
                        <?= e($avatarText) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text fw-semibold"><?= e($userName) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item" data-action="/logout" data-redirect="/login">
                                <i class="bi bi-box-arrow-right me-2"></i>Salir
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Cada vista superadmin renderiza su propio page-header -->
        <main class="app-main">
            <div class="app-content p-4">
                <?= $content ?>
            </div>
        </main>
    </div>
</div>

<script src="<?= e(asset('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('adminlte/js/adminlte.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/modules/saas.js')) ?>"></script>
<script src="<?= e(asset('js/modules/soporte.js')) ?>"></script>
<script src="<?= e(asset('js/modules/checklist.js')) ?>"></script>
<script src="/assets/js/sidebar.js"></script>
<script src="/assets/js/ui-components.js"></script>
</body>
</html>
