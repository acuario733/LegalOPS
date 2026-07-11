<?php

declare(strict_types=1);

$isSuperadmin = ($section ?? 'app') === 'superadmin';
$permissions  = is_array($currentUser ?? null) && is_array($currentUser['permissions'] ?? null)
    ? $currentUser['permissions']
    : [];
$can = static fn (string $permission): bool =>
    in_array('*', $permissions, true) || in_array($permission, $permissions, true);

// Estado activo calculado en PHP para evitar flash en carga inicial
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
$isActive = static fn (string $href): bool =>
    $currentPath === $href || ($href !== '/' && str_starts_with($currentPath, $href));
?>
<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="list">

    <?php if ($isSuperadmin): ?>

        <li class="nav-header">PLATAFORMA</li>

        <?php if ($can('firmas.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/firmas" class="nav-link <?= $isActive('/superadmin/firmas') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/firmas') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-buildings"></i><p>Firmas</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('planes.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/planes" class="nav-link <?= $isActive('/superadmin/planes') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/planes') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-boxes"></i><p>Planes y límites</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('monitoreo.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/monitoreo" class="nav-link <?= $isActive('/superadmin/monitoreo') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/monitoreo') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-activity"></i><p>Monitoreo</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('superadmin_usuarios.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/mis-usuarios" class="nav-link <?= $isActive('/superadmin/mis-usuarios') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/mis-usuarios') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-person-gear"></i><p>Mis usuarios</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('superadmin_roles.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/mis-roles" class="nav-link <?= $isActive('/superadmin/mis-roles') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/mis-roles') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-shield-lock"></i><p>Mis roles</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('auditoria.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/auditoria" class="nav-link <?= $isActive('/superadmin/auditoria') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/auditoria') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-journal-text"></i><p>Auditoría global</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('configuracion.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/catalogos" class="nav-link <?= $isActive('/superadmin/catalogos') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/catalogos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-list-check"></i><p>Catálogos</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('legal.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/legal" class="nav-link <?= $isActive('/superadmin/legal') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/legal') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-file-earmark-lock"></i><p>Documentos legales</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('soporte.ver_global')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/soporte" class="nav-link <?= $isActive('/superadmin/soporte') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/soporte') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-life-preserver"></i><p>Soporte global</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('checklist.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/superadmin/checklist" class="nav-link <?= $isActive('/superadmin/checklist') ? 'active' : '' ?>"
               <?= $isActive('/superadmin/checklist') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-clipboard-check"></i><p>Checklist owner</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('sistema.salud')): ?>
        <li class="nav-item" role="none">
            <a href="/health" class="nav-link <?= $isActive('/health') ? 'active' : '' ?>"
               <?= $isActive('/health') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-heart-pulse"></i><p>Estado del núcleo</p>
            </a>
        </li>
        <?php endif; ?>

    <?php else: ?>

        <!-- ── INICIO ──────────────────────────────────────────── -->
        <li class="nav-header">INICIO</li>

        <li class="nav-item" role="none">
            <a href="/dashboard" class="nav-link <?= $isActive('/dashboard') ? 'active' : '' ?>"
               <?= $isActive('/dashboard') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p>
            </a>
        </li>

        <?php if ($can('notificaciones.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/notificaciones" class="nav-link <?= $isActive('/notificaciones') ? 'active' : '' ?>"
               <?= $isActive('/notificaciones') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-bell"></i><p>Notificaciones</p>
            </a>
        </li>
        <?php endif; ?>

        <!-- ── CLIENTES ────────────────────────────────────────── -->
        <li class="nav-header">CLIENTES</li>

        <?php if ($can('clientes.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/clientes" class="nav-link <?= $isActive('/clientes') ? 'active' : '' ?>"
               <?= $isActive('/clientes') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-person-lines-fill"></i><p>Clientes</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('prospectos.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/prospectos" class="nav-link <?= $isActive('/prospectos') ? 'active' : '' ?>"
               <?= $isActive('/prospectos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-funnel"></i><p>Prospectos</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('intake.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/intake" class="nav-link <?= $isActive('/intake') ? 'active' : '' ?>"
               <?= $isActive('/intake') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-ui-checks"></i><p>Formularios Intake</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('booking.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/booking" class="nav-link <?= $isActive('/booking') ? 'active' : '' ?>"
               <?= $isActive('/booking') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-calendar2-check"></i><p>Reserva de citas</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('calendario.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/calendario" class="nav-link <?= $isActive('/calendario') ? 'active' : '' ?>"
               <?= $isActive('/calendario') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-calendar3"></i><p>Calendario</p>
            </a>
        </li>
        <?php endif; ?>

        <!-- ── OPERACIONES ─────────────────────────────────────── -->
        <li class="nav-header">OPERACIONES</li>

        <?php if ($can('casos.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/casos" class="nav-link <?= $isActive('/casos') ? 'active' : '' ?>"
               <?= $isActive('/casos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-briefcase"></i><p>Casos</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('terminos.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/terminos" class="nav-link <?= $isActive('/terminos') ? 'active' : '' ?>"
               <?= $isActive('/terminos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-clock-history"></i><p>Términos</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('audiencias.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/audiencias" class="nav-link <?= $isActive('/audiencias') ? 'active' : '' ?>"
               <?= $isActive('/audiencias') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-calendar-event"></i><p>Audiencias</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('tareas.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/tareas" class="nav-link <?= $isActive('/tareas') ? 'active' : '' ?>"
               <?= $isActive('/tareas') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-list-task"></i><p>Tareas</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('documentos.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/documentos" class="nav-link <?= $isActive('/documentos') ? 'active' : '' ?>"
               <?= $isActive('/documentos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-folder2-open"></i><p>Documentos</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('plantillas.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/plantillas" class="nav-link <?= $isActive('/plantillas') ? 'active' : '' ?>"
               <?= $isActive('/plantillas') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-file-earmark-richtext"></i><p>Plantillas</p>
            </a>
        </li>
        <?php endif; ?>

        <!-- ── FINANZAS ────────────────────────────────────────── -->
        <?php if ($can('finanzas.ver')): ?>
        <li class="nav-header">FINANZAS</li>

        <li class="nav-item" role="none">
            <a href="/finanzas/honorarios" class="nav-link <?= $isActive('/finanzas/honorarios') ? 'active' : '' ?>"
               <?= $isActive('/finanzas/honorarios') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-cash-coin"></i><p>Honorarios</p>
            </a>
        </li>
        <li class="nav-item" role="none">
            <a href="/finanzas/pagos" class="nav-link <?= $isActive('/finanzas/pagos') ? 'active' : '' ?>"
               <?= $isActive('/finanzas/pagos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-credit-card"></i><p>Pagos</p>
            </a>
        </li>
        <li class="nav-item" role="none">
            <a href="/finanzas/gastos" class="nav-link <?= $isActive('/finanzas/gastos') ? 'active' : '' ?>"
               <?= $isActive('/finanzas/gastos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-receipt"></i><p>Gastos</p>
            </a>
        </li>
        <li class="nav-item" role="none">
            <a href="/finanzas/saldos" class="nav-link <?= $isActive('/finanzas/saldos') ? 'active' : '' ?>"
               <?= $isActive('/finanzas/saldos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-calculator"></i><p>Saldos</p>
            </a>
        </li>
        <?php endif; ?>

        <!-- ── REPORTES ────────────────────────────────────────── -->
        <?php if ($can('reportes.ver') || $can('importaciones.ver')): ?>
        <li class="nav-header">REPORTES</li>

        <?php if ($can('reportes.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/reportes" class="nav-link <?= $isActive('/reportes') ? 'active' : '' ?>"
               <?= $isActive('/reportes') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-filetype-csv"></i><p>Reportes CSV</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('importaciones.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/importaciones" class="nav-link <?= $isActive('/importaciones') ? 'active' : '' ?>"
               <?= $isActive('/importaciones') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-upload"></i><p>Importaciones</p>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <!-- ── ADMINISTRACIÓN ──────────────────────────────────── -->
        <li class="nav-header">ADMINISTRACIÓN</li>

        <?php if ($can('usuarios.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/usuarios" class="nav-link <?= $isActive('/usuarios') ? 'active' : '' ?>"
               <?= $isActive('/usuarios') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-people"></i><p>Usuarios</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('roles.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/roles" class="nav-link <?= $isActive('/roles') ? 'active' : '' ?>"
               <?= $isActive('/roles') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-person-badge"></i><p>Roles</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('portal.autorizar')): ?>
        <li class="nav-item" role="none">
            <a href="/portal-autorizaciones" class="nav-link <?= $isActive('/portal-autorizaciones') ? 'active' : '' ?>"
               <?= $isActive('/portal-autorizaciones') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-person-check"></i><p>Portal cliente</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('soporte.ver_propio')): ?>
        <li class="nav-item" role="none">
            <a href="/soporte" class="nav-link <?= $isActive('/soporte') ? 'active' : '' ?>"
               <?= $isActive('/soporte') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-life-preserver"></i><p>Soporte</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('onboarding.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/onboarding" class="nav-link <?= $isActive('/onboarding') ? 'active' : '' ?>"
               <?= $isActive('/onboarding') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-rocket-takeoff"></i><p>Onboarding</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('configuracion.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/catalogos" class="nav-link <?= $isActive('/catalogos') ? 'active' : '' ?>"
               <?= $isActive('/catalogos') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-list-check"></i><p>Catálogos</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('auditoria.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/auditoria" class="nav-link <?= $isActive('/auditoria') ? 'active' : '' ?>"
               <?= $isActive('/auditoria') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-journal-text"></i><p>Auditoría</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('sesiones.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/sesiones" class="nav-link <?= $isActive('/sesiones') ? 'active' : '' ?>"
               <?= $isActive('/sesiones') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-shield-lock"></i><p>Sesiones</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('legal.ver')): ?>
        <li class="nav-item" role="none">
            <a href="/legal/pendientes" class="nav-link <?= $isActive('/legal/pendientes') ? 'active' : '' ?>"
               <?= $isActive('/legal/pendientes') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-file-check"></i><p>Aceptaciones</p>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($can('sistema.salud')): ?>
        <li class="nav-item" role="none">
            <a href="/health" class="nav-link <?= $isActive('/health') ? 'active' : '' ?>"
               <?= $isActive('/health') ? 'aria-current="page"' : '' ?>>
                <i class="nav-icon bi bi-heart-pulse"></i><p>Estado del núcleo</p>
            </a>
        </li>
        <?php endif; ?>

    <?php endif; ?>
</ul>
