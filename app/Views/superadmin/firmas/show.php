<?php

declare(strict_types=1);

$firma   = $firma   ?? [];
$nombre  = (string) ($firma['nombre']   ?? 'Firma');
$plan    = (string) ($firma['plan']     ?? 'Básico');
$estado  = (string) ($firma['estado']  ?? 'activa');
$firmaId = (int)    ($firma['id']      ?? 0);
$letra   = strtoupper(mb_substr($nombre, 0, 1));

$usuarios    = $usuarios    ?? [];
$pagos       = $pagos       ?? [];
$logs        = $logs        ?? [];
$uso         = $uso         ?? ['usuarios' => 0, 'max_usuarios' => 10, 'casos' => 0, 'max_casos' => 50, 'storage_mb' => 0, 'max_storage_mb' => 500, 'api_calls' => 0];
$limites     = $limites     ?? [];
$flags       = $flags       ?? [];

$planBadge = match (strtolower($plan)) {
    'profesional' => 'badge-profesional',
    'enterprise'  => 'badge-enterprise',
    default       => 'badge-basico',
};
$estadoBadge = match ($estado) {
    'activa'     => 'badge-activa',
    'suspendida' => 'badge-suspendida',
    default      => 'badge-trial',
};
?>

<!-- HEADER DE FICHA -->
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <div style="width:52px;height:52px;border-radius:var(--radius-md);background:var(--color-navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex-shrink:0;">
            <?= e($letra) ?>
        </div>
        <div>
            <h1 class="page-title"><?= e($nombre) ?></h1>
            <div class="d-flex align-items-center gap-2 mt-1">
                <span class="badge-status <?= $planBadge ?>"><?= e($plan) ?></span>
                <span class="badge-status <?= $estadoBadge ?>"><?= e(ucfirst($estado)) ?></span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/superadmin/firmas/<?= $firmaId ?>/edit" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i>Editar
        </a>
        <button type="button" class="btn btn-ghost" id="btn-impersonate"
                data-firma-id="<?= $firmaId ?>" data-firma-nombre="<?= e($nombre) ?>">
            <i class="bi bi-person-badge"></i>Impersonar
        </button>
        <div class="dropdown">
            <button class="btn btn-ghost dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="/superadmin/firmas/<?= $firmaId ?>/cambiar-plan">
                    <i class="bi bi-arrow-up-circle me-2"></i>Cambiar plan
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item danger" id="btn-suspender">
                    <i class="bi bi-pause-circle me-2"></i>Suspender firma
                </button></li>
            </ul>
        </div>
    </div>
</div>

<!-- TABS -->
<ul class="nav nav-tabs mb-4" id="firma-tabs" role="tablist">
    <?php
    $roles    = $roles    ?? [];
$permisos = $permisos ?? [];
$permisosPorModulo = [];
foreach ($permisos as $p) {
    $permisosPorModulo[$p['modulo']][] = $p;
}
ksort($permisosPorModulo);

$tabs = ['resumen' => 'Resumen', 'roles' => 'Roles', 'usuarios' => 'Usuarios', 'facturacion' => 'Facturación', 'logs' => 'Logs', 'configuracion' => 'Configuración'];
    $first = true;
    foreach ($tabs as $tabId => $tabLabel):
    ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $first ? 'active' : '' ?>"
                id="tab-<?= $tabId ?>"
                data-bs-toggle="tab"
                data-bs-target="#pane-<?= $tabId ?>"
                type="button" role="tab">
            <?= $tabLabel ?>
        </button>
    </li>
    <?php $first = false; endforeach; ?>
</ul>

<div class="tab-content">

    <!-- TAB: RESUMEN -->
    <div class="tab-pane fade show active" id="pane-resumen" role="tabpanel">
        <div class="row g-4">
            <!-- Datos de la firma -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Datos de la firma</div>
                    <div class="card-body">
                        <?php
                        $datosCampos = [
                            'Email'      => $firma['email']     ?? '',
                            'Teléfono'   => $firma['telefono']  ?? '',
                            'Dirección'  => $firma['direccion'] ?? '',
                            'País'       => $firma['pais']      ?? '',
                            'RFC / NIT'  => $firma['rfc']       ?? '',
                            'Dominio'    => $firma['slug']      ?? '',
                            'Timezone'   => $firma['timezone']  ?? '',
                            'Creada'     => $firma['created_at'] ?? '',
                        ];
                        foreach ($datosCampos as $campo => $valor):
                        if (empty($valor)) continue;
                        ?>
                        <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--color-border);">
                            <span style="font-size:var(--font-size-xs);font-weight:600;color:var(--color-text-secondary);width:100px;flex-shrink:0;padding-top:2px;">
                                <?= $campo ?>
                            </span>
                            <span style="font-size:var(--font-size-sm);"><?= e($valor) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <!-- Métricas de uso -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Métricas de uso</div>
                    <div class="card-body">
                        <?php
                        $metricas = [
                            ['label' => 'Usuarios',        'val' => $uso['usuarios'],    'max' => $uso['max_usuarios'],    'unit' => ''],
                            ['label' => 'Casos activos',   'val' => $uso['casos'],       'max' => $uso['max_casos'],       'unit' => ''],
                            ['label' => 'Almacenamiento',  'val' => $uso['storage_mb'],  'max' => $uso['max_storage_mb'],  'unit' => ' MB'],
                            ['label' => 'API calls/mes',   'val' => $uso['api_calls'],   'max' => 10000,                   'unit' => ''],
                        ];
                        foreach ($metricas as $m):
                            $pct = $m['max'] > 0 ? min(100, round($m['val'] / $m['max'] * 100)) : 0;
                            $barColor = $pct >= 90 ? 'var(--color-danger)' : ($pct >= 70 ? 'var(--color-warning)' : 'var(--color-green)');
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span style="font-size:var(--font-size-sm);font-weight:500;"><?= $m['label'] ?></span>
                                <span style="font-size:var(--font-size-xs);color:var(--color-text-secondary);">
                                    <?= number_format($m['val']) ?><?= $m['unit'] ?> / <?= number_format($m['max']) ?><?= $m['unit'] ?>
                                </span>
                            </div>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $barColor ?> !important;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: ROLES -->
    <div class="tab-pane fade" id="pane-roles" role="tabpanel">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="mb-0" style="font-size:var(--font-size-sm);color:var(--color-text-secondary);">
                Como superadmin puedes editar permisos de cualquier rol, incluso los protegidos.
            </p>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Permisos</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $rol): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:var(--font-size-sm);"><?= e($rol['nombre']) ?></div>
                                    <div>
                                        <code style="font-size:11px;color:var(--color-text-muted);"><?= e($rol['codigo']) ?></code>
                                        <?php if ((int)$rol['is_protected']): ?>
                                        <span class="badge-status badge-fijo ms-1">Protegido</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="font-size:var(--font-size-xs);color:var(--color-text-secondary);">
                                    <?= count($rol['permisos_ids']) ?> permiso<?= count($rol['permisos_ids']) !== 1 ? 's' : '' ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $rol['estado'] === 'activo' ? 'badge-confirmada' : 'badge-cancelada' ?>">
                                        <?= ucfirst(e($rol['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-ghost btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-sa-permisos"
                                            data-rol='<?= e(json_encode([
                                                'id'           => $rol['id'],
                                                'nombre'       => $rol['nombre'],
                                                'codigo'       => $rol['codigo'],
                                                'is_protected' => (int)$rol['is_protected'],
                                                'permisos_ids' => $rol['permisos_ids'],
                                            ], JSON_UNESCAPED_UNICODE)) ?>'>
                                        <i class="bi bi-shield-check me-1"></i>Permisos
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ($roles === []): ?>
                            <tr><td colspan="4" class="text-center py-5" style="color:var(--color-text-muted);">Sin roles configurados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: editar permisos de un rol (superadmin override) -->
        <div class="modal fade" id="modal-sa-permisos" tabindex="-1" aria-labelledby="label-sa-permisos">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="label-sa-permisos">Editar permisos del rol</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="form-sa-permisos" method="post" data-ajax-form>
                        <input type="hidden" name="_method" value="PATCH">
                        <div class="modal-body">
                            <div id="sa-protected-notice" class="d-none mb-3 p-3"
                                 style="background:rgba(234,179,8,0.1);border:1px solid var(--color-warning);border-radius:var(--radius-sm);font-size:var(--font-size-sm);">
                                <i class="bi bi-shield-exclamation me-2" style="color:var(--color-warning);"></i>
                                Rol protegido. Como superadmin puedes modificar sus permisos directamente.
                            </div>
                            <?php foreach ($permisosPorModulo as $modulo => $items): ?>
                            <div class="mb-3">
                                <p style="font-size:11px;font-weight:600;color:var(--color-navy);text-transform:capitalize;margin-bottom:var(--space-2);">
                                    <?= e(str_replace('_', ' ', $modulo)) ?>
                                </p>
                                <div class="row g-1">
                                    <?php foreach ($items as $p): ?>
                                    <div class="col-sm-6 col-lg-4">
                                        <label class="d-flex align-items-center gap-2 p-2"
                                               style="border:1px solid var(--color-border);border-radius:var(--radius-sm);cursor:pointer;font-size:12px;">
                                            <input class="form-check-input mb-0 flex-shrink-0 sa-perm-check"
                                                   type="checkbox"
                                                   name="permisos[]"
                                                   value="<?= (int)$p['id'] ?>"
                                                   data-perm-id="<?= (int)$p['id'] ?>">
                                            <span><code style="font-size:10px;color:var(--color-text-muted);"><?= e($p['codigo']) ?></code></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar permisos</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('modal-sa-permisos')?.addEventListener('show.bs.modal', function (e) {
            const btn  = e.relatedTarget;
            if (!btn) return;
            const rol  = JSON.parse(btn.getAttribute('data-rol') || '{}');
            const form = document.getElementById('form-sa-permisos');

            form.action = '/superadmin/firmas/<?= $firmaId ?>/roles/' + rol.id + '/permisos';
            document.getElementById('label-sa-permisos').textContent = 'Permisos: ' + rol.nombre;
            document.getElementById('sa-protected-notice').classList.toggle('d-none', !rol.is_protected);

            const permIds = new Set((rol.permisos_ids || []).map(Number));
            document.querySelectorAll('.sa-perm-check').forEach(cb => {
                cb.checked = permIds.has(Number(cb.dataset.permId));
            });
        });
        </script>

    </div><!-- /pane-roles -->

    <!-- TAB: USUARIOS -->
    <div class="tab-pane fade" id="pane-usuarios" role="tabpanel">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Último login</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:30px;height:30px;border-radius:var(--radius-full);background:var(--color-green-light);color:var(--color-green);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">
                                            <?= strtoupper(mb_substr((string)($u['name'] ?? 'U'), 0, 1)) ?>
                                        </div>
                                        <?= e($u['name'] ?? '') ?>
                                    </div>
                                </td>
                                <td><?= e($u['email'] ?? '') ?></td>
                                <td><?= e($u['role'] ?? '') ?></td>
                                <td style="color:var(--color-text-muted);font-size:var(--font-size-xs);"><?= e($u['last_login'] ?? 'Nunca') ?></td>
                                <td>
                                    <span class="badge-status <?= ($u['active'] ?? false) ? 'badge-activa' : 'badge-suspendida' ?>">
                                        <?= ($u['active'] ?? false) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($usuarios)): ?>
                            <tr><td colspan="5" class="text-center py-4" style="color:var(--color-text-muted);">Sin usuarios</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: FACTURACIÓN -->
    <div class="tab-pane fade" id="pane-facturacion" role="tabpanel">
        <?php if (!empty($proximoCobro)): ?>
        <div class="card mb-4" style="border-left:4px solid var(--color-green);">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="stat-label">Próximo cobro</p>
                    <div class="stat-value">$<?= number_format((float)($proximoCobro['monto'] ?? 0), 2) ?></div>
                    <span style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
                        <?= e($proximoCobro['fecha'] ?? '') ?> · Plan <?= e($proximoCobro['plan'] ?? '') ?>
                    </span>
                </div>
                <i class="bi bi-calendar-check" style="font-size:2rem;color:var(--color-green);opacity:0.5;"></i>
            </div>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Período</th>
                                <th>Plan</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $pago): ?>
                            <?php
                            $estadoPago = strtolower($pago['estado'] ?? 'pendiente');
                            $badgePago  = match ($estadoPago) {
                                'pagado'  => 'badge-confirmada',
                                'fallido' => 'badge-cancelada',
                                default   => 'badge-pendiente',
                            };
                            ?>
                            <tr>
                                <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);"><?= e($pago['fecha'] ?? '') ?></td>
                                <td><?= e($pago['periodo'] ?? '') ?></td>
                                <td><?= e($pago['plan'] ?? '') ?></td>
                                <td style="font-weight:600;">$<?= number_format((float)($pago['monto'] ?? 0), 2) ?></td>
                                <td><span class="badge-status <?= $badgePago ?>"><?= e(ucfirst($estadoPago)) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pagos)): ?>
                            <tr><td colspan="5" class="text-center py-4" style="color:var(--color-text-muted);">Sin historial de pagos</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: LOGS -->
    <div class="tab-pane fade" id="pane-logs" role="tabpanel">
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <input type="text" class="form-control" placeholder="Filtrar por módulo…" id="logs-filter-module">
            </div>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="logs-filter-date">
            </div>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Módulo</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);white-space:nowrap;"><?= e($log['fecha'] ?? '') ?></td>
                                <td><?= e($log['usuario'] ?? '') ?></td>
                                <td><?= e($log['accion'] ?? '') ?></td>
                                <td>
                                    <span class="badge-status badge-fijo"><?= e($log['modulo'] ?? '') ?></span>
                                </td>
                                <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);"><?= e($log['ip'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center py-4" style="color:var(--color-text-muted);">Sin logs</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: CONFIGURACIÓN -->
    <div class="tab-pane fade" id="pane-configuracion" role="tabpanel">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Límites del plan</div>
                    <div class="card-body">
                        <form id="form-limites" action="/superadmin/firmas/<?= $firmaId ?>/limites" method="post">
                            <div class="mb-3">
                                <label class="form-label">Máx. usuarios</label>
                                <input type="number" class="form-control" name="max_usuarios" value="<?= (int)($limites['max_usuarios'] ?? 10) ?>" min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Máx. casos</label>
                                <input type="number" class="form-control" name="max_casos" value="<?= (int)($limites['max_casos'] ?? 50) ?>" min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Almacenamiento máx. (MB)</label>
                                <input type="number" class="form-control" name="max_storage_mb" value="<?= (int)($limites['max_storage_mb'] ?? 500) ?>" min="100">
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar límites</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Módulos habilitados</div>
                    <div class="card-body">
                        <?php
                        $modulos = [
                            'api'       => 'API pública',
                            'intake'    => 'Formularios Intake',
                            'booking'   => 'Reserva de citas',
                            'plantillas'=> 'Plantillas',
                            'webhooks'  => 'Webhooks',
                        ];
                        foreach ($modulos as $key => $label):
                        ?>
                        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--color-border);">
                            <label class="form-label mb-0" for="flag-<?= $key ?>"><?= $label ?></label>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="flag-<?= $key ?>"
                                       data-firma-flag="<?= $firmaId ?>"
                                       data-flag-key="<?= $key ?>"
                                       <?= !empty($flags[$key]) ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Zona peligrosa -->
                <div class="card" style="border-color:var(--color-danger);">
                    <div class="card-header" style="color:var(--color-danger);border-color:var(--color-danger);">
                        <i class="bi bi-exclamation-triangle me-2"></i>Zona peligrosa
                    </div>
                    <div class="card-body d-flex flex-column gap-2">
                        <button type="button" class="btn btn-outline-primary" id="btn-reset-demo">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Resetear datos de demo
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btn-suspender-firma"
                                style="border-color:var(--color-warning);color:var(--color-warning);">
                            <i class="bi bi-pause-circle me-2"></i>Suspender firma
                        </button>
                        <button type="button" class="btn" id="btn-eliminar-firma"
                                style="background:var(--color-danger);color:#fff;border-color:var(--color-danger);">
                            <i class="bi bi-trash me-2"></i>Eliminar firma permanentemente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /tab-content -->

<script>
// Impersonar firma
document.getElementById('btn-impersonate')?.addEventListener('click', function () {
    const nombre = this.dataset.firmaNombre;
    const id     = this.dataset.firmaId;
    if (window.LegalUI) {
        LegalUI.confirm(
            `¿Deseas ver el sistema como la firma <strong>${nombre}</strong>?<br>
             <small>Se mostrará un banner rojo mientras estés impersonando.</small>`,
            () => { window.location.href = '/superadmin/firmas/' + id + '/impersonate'; },
            { title: 'Impersonar firma', confirmText: 'Impersonar' }
        );
    }
});

// Eliminar firma — doble confirmación
document.getElementById('btn-eliminar-firma')?.addEventListener('click', function () {
    const nombre = <?= json_encode($nombre) ?>;
    if (window.LegalUI) {
        LegalUI.confirm(
            `Esta acción es <strong>irreversible</strong>. Escribe el nombre de la firma para confirmar:<br>
             <input type="text" class="form-control mt-2" id="confirm-firma-nombre" placeholder="${nombre}">`,
            () => {
                const val = document.getElementById('confirm-firma-nombre')?.value;
                if (val !== nombre) {
                    LegalUI.toast('El nombre no coincide. Operación cancelada.', 'error');
                    return;
                }
                window.location.href = '/superadmin/firmas/<?= $firmaId ?>/delete';
            },
            { title: 'Eliminar firma', confirmText: 'Eliminar permanentemente', type: 'danger' }
        );
    }
});

// Flags de módulos — guardar via PATCH
document.querySelectorAll('[data-firma-flag]').forEach(toggle => {
    toggle.addEventListener('change', async function () {
        const firmaId = this.dataset.firmaFlag;
        const key     = this.dataset.flagKey;
        const value   = this.checked;
        try {
            const res = await fetch(`/superadmin/firmas/${firmaId}/flags`, {
                method:  'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ flag: key, value }),
            });
            if (!res.ok) throw new Error('Error al guardar');
            if (window.LegalUI) LegalUI.toast(`Módulo "${key}" ${value ? 'habilitado' : 'deshabilitado'}`, 'success');
        } catch {
            this.checked = !value;
            if (window.LegalUI) LegalUI.toast('No se pudo guardar el cambio', 'error');
        }
    });
});
</script>
