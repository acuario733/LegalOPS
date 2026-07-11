<?php

declare(strict_types=1);

$planes = $planes ?? [];

// Íconos y colores visuales por posición del plan
$planVisual = [
    0 => ['icon' => 'bi-box',        'color' => '#475569', 'bg' => '#F1F5F9', 'badge' => 'badge-basico'],
    1 => ['icon' => 'bi-stars',      'color' => '#2563EB', 'bg' => '#DBEAFE', 'badge' => 'badge-profesional'],
    2 => ['icon' => 'bi-gem',        'color' => '#92400E', 'bg' => '#FEF3C7', 'badge' => 'badge-enterprise'],
];

$modulos = [
    'api'          => 'API pública',
    'intake'       => 'Intake forms',
    'booking'      => 'Reserva de citas',
    'plantillas'   => 'Plantillas',
    'webhooks'     => 'Webhooks',
    'time_tracking'=> 'Time Tracking',
];

$recursos = ['usuarios','roles','catalogos','clientes','prospectos','casos','terminos','audiencias','tareas','documentos','honorarios','pagos','gastos','exportaciones','importaciones','tickets_soporte'];
?>

<div data-feedback hidden></div>

<!-- HEADER -->
<div class="page-header">
    <div>
        <h1 class="page-title">Planes</h1>
        <p class="page-subtitle">Gestiona los planes de suscripción del SaaS</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-nuevo-plan">
        <i class="bi bi-plus-lg"></i>Nuevo plan
    </button>
</div>

<!-- CARDS DE PLANES ACTUALES -->
<div class="row g-4 mb-5">
    <?php foreach ($planes as $idx => $plan): ?>
    <?php $vis = $planVisual[$idx % 3] ?? $planVisual[0]; ?>
    <div class="col-md-4">
        <div class="card h-100 position-relative">
            <!-- Badge de firmas activas -->
            <div style="position:absolute;top:16px;right:16px;">
                <span class="badge-status <?= $vis['badge'] ?>">
                    <?= (int)($plan['firmas_count'] ?? 0) ?> firma<?= ((int)($plan['firmas_count'] ?? 0)) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <div class="card-body">
                <!-- Ícono del plan -->
                <div style="width:52px;height:52px;border-radius:var(--radius-md);background:<?= $vis['bg'] ?>;display:flex;align-items:center;justify-content:center;color:<?= $vis['color'] ?>;font-size:22px;margin-bottom:var(--space-4);">
                    <i class="bi <?= $vis['icon'] ?>"></i>
                </div>

                <h3 style="font-size:var(--font-size-lg);font-weight:700;color:var(--color-navy);margin-bottom:var(--space-1);">
                    <?= e($plan['nombre']) ?>
                </h3>
                <p style="font-size:var(--font-size-xs);color:var(--color-text-muted);margin-bottom:var(--space-4);">
                    <code style="background:var(--color-bg);padding:2px 6px;border-radius:4px;"><?= e($plan['codigo']) ?></code>
                </p>

                <?php if (!empty($plan['descripcion'])): ?>
                <p style="font-size:var(--font-size-sm);color:var(--color-text-secondary);margin-bottom:var(--space-4);">
                    <?= e($plan['descripcion']) ?>
                </p>
                <?php endif; ?>

                <!-- Límites -->
                <ul style="list-style:none;padding:0;margin:0 0 var(--space-5);font-size:var(--font-size-xs);">
                    <?php
                    $limitesDelPlan = $plan['limites'] ?? [];
                    $resumenLimites = [
                        'Usuarios'  => $limitesDelPlan['usuarios']['limite']  ?? '∞',
                        'Casos'     => $limitesDelPlan['casos']['limite']     ?? '∞',
                        'Clientes'  => $limitesDelPlan['clientes']['limite']  ?? '∞',
                        'Documentos'=> $limitesDelPlan['documentos']['limite']?? '∞',
                    ];
                    foreach ($resumenLimites as $lbl => $val):
                    ?>
                    <li style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid var(--color-border);">
                        <i class="bi bi-check2" style="color:<?= $vis['color'] ?>;flex-shrink:0;"></i>
                        <span style="color:var(--color-text-secondary);"><?= $lbl ?>:</span>
                        <strong style="margin-left:auto;"><?= $val ?></strong>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Botón editar -->
                <button type="button" class="btn btn-outline-primary w-100"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-edit-plan"
                        data-plan='<?= e(json_encode($plan, JSON_UNESCAPED_UNICODE)) ?>'>
                    <i class="bi bi-pencil"></i>Editar plan
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($planes)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5" style="color:var(--color-text-muted);">
                <i class="bi bi-box" style="font-size:3rem;opacity:0.4;"></i>
                <p class="mt-2 mb-0">No hay planes configurados. Crea el primero.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- TABLA DE PLANES (vista técnica) -->
<div class="card">
    <div class="card-header">Lista completa de planes</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Firmas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planes as $plan): ?>
                    <tr>
                        <td><code style="background:var(--color-bg);padding:2px 6px;border-radius:4px;font-size:12px;"><?= e($plan['codigo']) ?></code></td>
                        <td style="font-weight:500;"><?= e($plan['nombre']) ?></td>
                        <td>
                            <span class="badge-status <?= $plan['estado'] === 'activo' ? 'badge-activa' : 'badge-suspendida' ?>">
                                <?= ucfirst(e($plan['estado'])) ?>
                            </span>
                        </td>
                        <td><?= (int)($plan['firmas_count'] ?? 0) ?></td>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal-edit-plan"
                                    data-plan='<?= e(json_encode($plan, JSON_UNESCAPED_UNICODE)) ?>'>
                                <i class="bi bi-pencil"></i>Editar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($planes)): ?>
                    <tr><td colspan="5" class="text-center py-4" style="color:var(--color-text-muted);">No hay planes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: NUEVO PLAN -->
<div class="modal fade" id="modal-nuevo-plan" tabindex="-1" aria-labelledby="modal-nuevo-plan-label">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-nuevo-plan-label">Nuevo plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/superadmin/planes" method="post" data-ajax-form>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Código</label>
                            <input class="form-control" name="codigo" required placeholder="basico">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="nombre" required placeholder="Plan Básico">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="2"></textarea>
                        </div>
                    </div>
                    <h6 style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-secondary);margin-bottom:var(--space-3);">
                        Límites de recursos
                    </h6>
                    <div class="row g-2">
                        <?php foreach ($recursos as $resource): ?>
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label" style="font-size:11px;"><?= ucfirst(str_replace('_', ' ', $resource)) ?></label>
                            <input class="form-control form-control-sm" name="limites[<?= e($resource) ?>][limite]" type="number" min="0" placeholder="∞">
                            <input type="hidden" name="limites[<?= e($resource) ?>][politica]" value="block">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <h6 style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-secondary);margin:var(--space-5) 0 var(--space-3);">
                        Módulos habilitados
                    </h6>
                    <div class="row g-2">
                        <?php foreach ($modulos as $key => $label): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="d-flex align-items-center gap-2 p-2" style="border:1px solid var(--color-border);border-radius:var(--radius-sm);">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= e($key) ?>" id="new-mod-<?= e($key) ?>">
                                </div>
                                <label class="form-check-label mb-0" for="new-mod-<?= e($key) ?>" style="font-size:var(--font-size-sm);cursor:pointer;">
                                    <?= $label ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR PLAN -->
<div class="modal fade" id="modal-edit-plan" tabindex="-1" aria-labelledby="modal-edit-plan-label">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-edit-plan-label">Editar plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-edit-plan" method="post" data-ajax-form>
                <input type="hidden" name="_method" value="PATCH">
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Código</label>
                            <input class="form-control" name="codigo" id="edit-codigo" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" name="nombre" id="edit-nombre" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" id="edit-estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="edit-descripcion" rows="2"></textarea>
                        </div>
                    </div>
                    <h6 style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-secondary);margin-bottom:var(--space-3);">
                        Límites de recursos
                    </h6>
                    <div class="row g-2" id="edit-limites-container">
                        <?php foreach ($recursos as $resource): ?>
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label" style="font-size:11px;"><?= ucfirst(str_replace('_', ' ', $resource)) ?></label>
                            <input class="form-control form-control-sm" name="limites[<?= e($resource) ?>][limite]" data-recurso="<?= e($resource) ?>" type="number" min="0" placeholder="∞">
                            <input type="hidden" name="limites[<?= e($resource) ?>][politica]" value="block">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Poblar modal de edición con datos del plan seleccionado
document.getElementById('modal-edit-plan')?.addEventListener('show.bs.modal', (e) => {
    const btn  = e.relatedTarget;
    if (!btn) return;
    const plan = JSON.parse(btn.getAttribute('data-plan') || '{}');
    const form = document.getElementById('form-edit-plan');

    form.action = '/superadmin/planes/' + plan.id;
    document.getElementById('edit-codigo').value      = plan.codigo      || '';
    document.getElementById('edit-nombre').value      = plan.nombre      || '';
    document.getElementById('edit-descripcion').value = plan.descripcion || '';
    document.getElementById('edit-estado').value      = plan.estado      || 'activo';

    // Poblar límites
    const limites = plan.limites || {};
    form.querySelectorAll('[data-recurso]').forEach(input => {
        const rec = input.getAttribute('data-recurso');
        input.value = limites[rec]?.limite ?? '';
    });
});
</script>
