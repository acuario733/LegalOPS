<?php

declare(strict_types=1);

$isSuperadmin = ($section ?? 'app') === 'superadmin';
$permissions = is_array($currentUser ?? null) && is_array($currentUser['permissions'] ?? null) ? $currentUser['permissions'] : [];
$can = static fn (string $permission): bool => in_array('*', $permissions, true) || in_array($permission, $permissions, true);
?>
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="/health" class="brand-link text-decoration-none">
            <span class="legalops-brand-mark me-2">L</span>
            <span class="brand-text fw-semibold">LegalOPS Cloud</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Navegación principal">
                <li class="nav-header"><?= $isSuperadmin ? 'PLATAFORMA' : 'SISTEMA' ?></li>
                <li class="nav-item"><a href="/health" class="nav-link active"><i class="nav-icon bi bi-heart-pulse"></i><p>Estado del núcleo</p></a></li>
                <?php if ($isSuperadmin): ?>
                    <?php if ($can('firmas.ver')): ?><li class="nav-item"><a href="/superadmin/firmas" class="nav-link"><i class="nav-icon bi bi-buildings"></i><p>Firmas</p></a></li><?php endif; ?>
                    <?php if ($can('planes.ver')): ?><li class="nav-item"><a href="/superadmin/planes" class="nav-link"><i class="nav-icon bi bi-boxes"></i><p>Planes y límites</p></a></li><?php endif; ?>
                    <?php if ($can('auditoria.ver')): ?><li class="nav-item"><a href="/superadmin/auditoria" class="nav-link"><i class="nav-icon bi bi-journal-text"></i><p>Auditoría global</p></a></li><?php endif; ?>
                    <?php if ($can('configuracion.ver')): ?><li class="nav-item"><a href="/superadmin/catalogos" class="nav-link"><i class="nav-icon bi bi-list-check"></i><p>Catálogos</p></a></li><?php endif; ?>
                    <?php if ($can('legal.ver')): ?><li class="nav-item"><a href="/superadmin/legal" class="nav-link"><i class="nav-icon bi bi-file-earmark-lock"></i><p>Documentos legales</p></a></li><?php endif; ?>
                    <?php if ($can('soporte.ver_global')): ?><li class="nav-item"><a href="/superadmin/soporte" class="nav-link"><i class="nav-icon bi bi-life-preserver"></i><p>Soporte global</p></a></li><?php endif; ?>
                    <?php if ($can('checklist.ver')): ?><li class="nav-item"><a href="/superadmin/checklist" class="nav-link"><i class="nav-icon bi bi-clipboard-check"></i><p>Checklist owner</p></a></li><?php endif; ?>
                <?php else: ?>
                    <li class="nav-item"><a href="/dashboard" class="nav-link"><i class="nav-icon bi bi-speedometer2"></i><p>Dashboard</p></a></li>
                    <?php if ($can('notificaciones.ver')): ?><li class="nav-item"><a href="/notificaciones" class="nav-link"><i class="nav-icon bi bi-bell"></i><p>Notificaciones</p></a></li><?php endif; ?>
                    <?php if ($can('usuarios.ver')): ?><li class="nav-item"><a href="/usuarios" class="nav-link"><i class="nav-icon bi bi-people"></i><p>Usuarios</p></a></li><?php endif; ?>
                    <?php if ($can('roles.ver')): ?><li class="nav-item"><a href="/roles" class="nav-link"><i class="nav-icon bi bi-person-badge"></i><p>Roles</p></a></li><?php endif; ?>
                    <?php if ($can('clientes.ver')): ?><li class="nav-item"><a href="/clientes" class="nav-link"><i class="nav-icon bi bi-person-lines-fill"></i><p>Clientes</p></a></li><?php endif; ?>
                    <?php if ($can('prospectos.ver')): ?><li class="nav-item"><a href="/prospectos" class="nav-link"><i class="nav-icon bi bi-funnel"></i><p>Prospectos</p></a></li><?php endif; ?>
                    <?php if ($can('intake.ver')): ?><li class="nav-item"><a href="/intake" class="nav-link"><i class="nav-icon bi bi-ui-checks"></i><p>Formularios Intake</p></a></li><?php endif; ?>
                    <?php if ($can('booking.ver')): ?><li class="nav-item"><a href="/booking" class="nav-link"><i class="nav-icon bi bi-calendar2-check"></i><p>Reserva de citas</p></a></li><?php endif; ?>
                    <?php if ($can('casos.ver')): ?><li class="nav-item"><a href="/casos" class="nav-link"><i class="nav-icon bi bi-briefcase"></i><p>Casos</p></a></li><?php endif; ?>
                    <?php if ($can('terminos.ver')): ?><li class="nav-item"><a href="/terminos" class="nav-link"><i class="nav-icon bi bi-clock-history"></i><p>Terminos</p></a></li><?php endif; ?>
                    <?php if ($can('audiencias.ver')): ?><li class="nav-item"><a href="/audiencias" class="nav-link"><i class="nav-icon bi bi-calendar-event"></i><p>Audiencias</p></a></li><?php endif; ?>
                    <?php if ($can('tareas.ver')): ?><li class="nav-item"><a href="/tareas" class="nav-link"><i class="nav-icon bi bi-list-task"></i><p>Tareas</p></a></li><?php endif; ?>
                    <?php if ($can('documentos.ver')): ?><li class="nav-item"><a href="/documentos" class="nav-link"><i class="nav-icon bi bi-folder2-open"></i><p>Documentos</p></a></li><?php endif; ?>
                    <?php if ($can('plantillas.ver')): ?><li class="nav-item"><a href="/plantillas" class="nav-link"><i class="nav-icon bi bi-file-earmark-richtext"></i><p>Plantillas</p></a></li><?php endif; ?>
                    <?php if ($can('portal.autorizar')): ?><li class="nav-item"><a href="/portal-autorizaciones" class="nav-link"><i class="nav-icon bi bi-person-check"></i><p>Portal cliente</p></a></li><?php endif; ?>
                    <?php if ($can('finanzas.ver')): ?><li class="nav-item"><a href="/finanzas/honorarios" class="nav-link"><i class="nav-icon bi bi-cash-coin"></i><p>Honorarios</p></a></li><?php endif; ?>
                    <?php if ($can('finanzas.ver')): ?><li class="nav-item"><a href="/finanzas/pagos" class="nav-link"><i class="nav-icon bi bi-credit-card"></i><p>Pagos</p></a></li><?php endif; ?>
                    <?php if ($can('finanzas.ver')): ?><li class="nav-item"><a href="/finanzas/gastos" class="nav-link"><i class="nav-icon bi bi-receipt"></i><p>Gastos</p></a></li><?php endif; ?>
                    <?php if ($can('finanzas.ver')): ?><li class="nav-item"><a href="/finanzas/saldos" class="nav-link"><i class="nav-icon bi bi-calculator"></i><p>Saldos</p></a></li><?php endif; ?>
                    <?php if ($can('reportes.ver')): ?><li class="nav-item"><a href="/reportes" class="nav-link"><i class="nav-icon bi bi-filetype-csv"></i><p>Reportes CSV</p></a></li><?php endif; ?>
                    <?php if ($can('importaciones.ver')): ?><li class="nav-item"><a href="/importaciones" class="nav-link"><i class="nav-icon bi bi-upload"></i><p>Importaciones</p></a></li><?php endif; ?>
                    <?php if ($can('soporte.ver_propio')): ?><li class="nav-item"><a href="/soporte" class="nav-link"><i class="nav-icon bi bi-life-preserver"></i><p>Soporte</p></a></li><?php endif; ?>
                    <?php if ($can('onboarding.ver')): ?><li class="nav-item"><a href="/onboarding" class="nav-link"><i class="nav-icon bi bi-rocket-takeoff"></i><p>Onboarding</p></a></li><?php endif; ?>
                    <?php if ($can('configuracion.ver')): ?><li class="nav-item"><a href="/catalogos" class="nav-link"><i class="nav-icon bi bi-list-check"></i><p>Catálogos</p></a></li><?php endif; ?>
                    <?php if ($can('auditoria.ver')): ?><li class="nav-item"><a href="/auditoria" class="nav-link"><i class="nav-icon bi bi-journal-text"></i><p>Auditoría</p></a></li><?php endif; ?>
                    <?php if ($can('sesiones.ver')): ?><li class="nav-item"><a href="/sesiones" class="nav-link"><i class="nav-icon bi bi-shield-lock"></i><p>Sesiones</p></a></li><?php endif; ?>
                    <?php if ($can('legal.ver')): ?><li class="nav-item"><a href="/legal/pendientes" class="nav-link"><i class="nav-icon bi bi-file-check"></i><p>Aceptaciones</p></a></li><?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</aside>
