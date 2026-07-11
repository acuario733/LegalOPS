<?php declare(strict_types=1);
$dateValue = static fn (mixed $value): string => $value === null || $value === '' ? '' : substr((string) $value, 0, 10);
$dateTimeValue = static fn (mixed $value): string => $value === null || $value === '' ? '' : str_replace(' ', 'T', substr((string) $value, 0, 16));
$billingStatuses = ['activa', 'prueba', 'pago_vencido'];
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Nueva firma</h2></div>
            <div class="card-body">
                <form action="/superadmin/firmas" method="post" data-ajax-form>
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input class="form-control" name="slug" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Zona horaria</label>
                        <input class="form-control" name="timezone" value="America/Bogota" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Crear firma</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Asignar o cambiar plan</h2></div>
            <div class="card-body">
                <form action="/superadmin/planes/asignar" method="post" data-ajax-form>
                    <div class="mb-3">
                        <label class="form-label">Firma</label>
                        <select class="form-select" name="firma_id" required>
                            <?php foreach ($firmas as $firma): ?>
                                <option value="<?= (int) $firma['id'] ?>"><?= e($firma['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Plan</label>
                        <select class="form-select" name="plan_id" required>
                            <?php foreach ($planes as $plan): ?>
                                <option value="<?= (int) $plan['id'] ?>"><?= e($plan['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha efectiva</label>
                        <input class="form-control" name="effective_at" type="datetime-local">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Proxima renovacion</label>
                        <input class="form-control" name="renews_at" type="date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado inicial</label>
                        <select class="form-select" name="estado_comercial">
                            <option value="activa">Activa</option>
                            <option value="prueba">Prueba</option>
                        </select>
                    </div>
                    <input type="hidden" name="billing_period" value="monthly">
                    <div class="mb-3">
                        <label class="form-label">Fin de prueba</label>
                        <input class="form-control" name="trial_ends_at" type="date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vencimiento de pago</label>
                        <input class="form-control" name="payment_due_at" type="date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fin de gracia</label>
                        <input class="form-control" name="grace_ends_at" type="date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Suspension automatica</label>
                        <input class="form-control" name="auto_suspend_at" type="datetime-local">
                    </div>
                    <input type="hidden" name="proration_policy" value="manual_review">
                    <div class="mb-3">
                        <label class="form-label">Regla de prorrateo</label>
                        <textarea class="form-control" name="proration_note" rows="2" maxlength="500">Prorrateo sujeto a revision administrativa; no calcula cobros automaticos.</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo comercial</label>
                        <textarea class="form-control" name="motivo" rows="3" minlength="5" maxlength="500" required></textarea>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Registrar cambio</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <h2 class="card-title mb-0">Firmas registradas</h2>
                <button class="btn btn-sm btn-outline-warning" data-action="/superadmin/firmas/suspensiones-automaticas" data-confirm="Revisar suspensiones automaticas por no pago?">Revisar suspensiones</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Estado comercial</th>
                        <th>Suscripcion actual</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($firmas as $firma): ?>
                        <?php $history = $historialComercial[(int) $firma['id']] ?? []; ?>
                        <?php $status = $commercialStatus->normalize($firma['estado'] ?? null); ?>
                        <?php $statusClass = $commercialStatus->allowsOperation($status) ? 'success' : 'warning'; ?>
                        <tr>
                            <td>
                                <strong><?= e($firma['nombre']) ?></strong>
                                <div class="small text-secondary"><?= e($firma['slug']) ?></div>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= e($statusClass) ?>"><?= e($commercialStatus->label($status)) ?></span>
                                <div class="small text-secondary"><?= e($commercialStatus->consequence($status)) ?></div>
                                <?php if (!empty($firma['suspended_at'])): ?>
                                    <div class="small text-secondary">Suspendida desde <?= e($firma['suspended_at']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($firma['plan_nombre'])): ?>
                                    <strong><?= e($firma['plan_nombre']) ?></strong>
                                    <div class="small text-secondary">Codigo <?= e($firma['plan_codigo'] ?? '') ?></div>
                                    <div class="small text-secondary">Vigente desde <?= e($firma['plan_effective_at'] ?? $firma['plan_starts_at'] ?? '') ?></div>
                                    <div class="small text-secondary">Renovacion <?= e($firma['plan_renews_at'] ?? 'Sin fecha') ?></div>
                                    <div class="small text-secondary">Corte mensual dia <?= e((string) ($firma['plan_billing_anchor_day'] ?? 'Sin dato')) ?></div>
                                    <?php if (!empty($firma['plan_trial_ends_at'])): ?>
                                        <div class="small text-secondary">Prueba hasta <?= e($firma['plan_trial_ends_at']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($firma['plan_payment_due_at'])): ?>
                                        <div class="small text-secondary">Pago vence <?= e($firma['plan_payment_due_at']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($firma['plan_grace_ends_at'])): ?>
                                        <div class="small text-secondary">Gracia hasta <?= e($firma['plan_grace_ends_at']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($firma['plan_auto_suspend_at'])): ?>
                                        <div class="small text-secondary">Suspension automatica <?= e($firma['plan_auto_suspend_at']) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-secondary">Prorrateo <?= e($firma['plan_proration_policy'] ?? 'manual_review') ?></div>
                                    <?php if (!empty($firma['plan_proration_note'])): ?>
                                        <div class="small text-secondary"><?= e($firma['plan_proration_note']) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-secondary">Responsable <?= e($firma['plan_assigned_by_nombre'] ?? 'No registrado') ?></div>
                                    <?php if (!empty($firma['plan_motivo'])): ?>
                                        <div class="small text-secondary">Motivo: <?= e($firma['plan_motivo']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-secondary">Sin plan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/superadmin/firmas/<?= (int) $firma['id'] ?>" class="btn btn-sm btn-outline-primary mb-1">
                                    <i class="bi bi-eye"></i> Ver detalle
                                </a>
                                <?php if ($status === 'activa' || $status === 'prueba'): ?>
                                    <button class="btn btn-sm btn-outline-warning" data-action="/superadmin/firmas/<?= (int) $firma['id'] ?>/suspender" data-prompt="Motivo de suspension" data-confirm="Suspender esta firma?">Suspender</button>
                                <?php elseif ($status === 'suspendida'): ?>
                                    <button class="btn btn-sm btn-outline-success" data-action="/superadmin/firmas/<?= (int) $firma['id'] ?>/reactivar" data-prompt="Motivo de reactivacion">Reactivar</button>
                                <?php endif; ?>
                                <details class="mt-2">
                                    <summary class="small">Editar</summary>
                                    <form class="mt-2" action="/superadmin/firmas/<?= (int) $firma['id'] ?>" method="post" data-ajax-form>
                                        <input type="hidden" name="_method" value="PATCH">
                                        <input class="form-control form-control-sm mb-1" name="nombre" value="<?= e($firma['nombre']) ?>">
                                        <input class="form-control form-control-sm mb-1" name="slug" value="<?= e($firma['slug']) ?>">
                                        <input class="form-control form-control-sm mb-1" name="timezone" value="<?= e($firma['timezone']) ?>">
                                        <button class="btn btn-sm btn-primary">Guardar</button>
                                    </form>
                                </details>
                                <details class="mt-2">
                                    <summary class="small">Facturacion</summary>
                                    <?php if (!empty($firma['firma_plan_id'])): ?>
                                        <form class="mt-2" action="/superadmin/firmas/<?= (int) $firma['id'] ?>/facturacion" method="post" data-ajax-form>
                                            <select class="form-select form-select-sm mb-1" name="estado_comercial">
                                                <?php foreach ($billingStatuses as $option): ?>
                                                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($commercialStatus->label($option)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="billing_period" value="monthly">
                                            <input class="form-control form-control-sm mb-1" name="billing_anchor_day" type="number" min="1" max="31" value="<?= e((string) ($firma['plan_billing_anchor_day'] ?? '')) ?>" placeholder="Dia de corte">
                                            <input class="form-control form-control-sm mb-1" name="renews_at" type="date" value="<?= e($dateValue($firma['plan_renews_at'] ?? null)) ?>">
                                            <input class="form-control form-control-sm mb-1" name="trial_ends_at" type="date" value="<?= e($dateValue($firma['plan_trial_ends_at'] ?? null)) ?>">
                                            <input class="form-control form-control-sm mb-1" name="payment_due_at" type="date" value="<?= e($dateValue($firma['plan_payment_due_at'] ?? null)) ?>">
                                            <input class="form-control form-control-sm mb-1" name="grace_ends_at" type="date" value="<?= e($dateValue($firma['plan_grace_ends_at'] ?? null)) ?>">
                                            <input class="form-control form-control-sm mb-1" name="auto_suspend_at" type="datetime-local" value="<?= e($dateTimeValue($firma['plan_auto_suspend_at'] ?? null)) ?>">
                                            <select class="form-select form-select-sm mb-1" name="proration_policy">
                                                <option value="manual_review" <?= ($firma['plan_proration_policy'] ?? 'manual_review') === 'manual_review' ? 'selected' : '' ?>>Prorrateo manual</option>
                                                <option value="none" <?= ($firma['plan_proration_policy'] ?? '') === 'none' ? 'selected' : '' ?>>Sin prorrateo</option>
                                            </select>
                                            <textarea class="form-control form-control-sm mb-1" name="proration_note" rows="2" maxlength="500"><?= e($firma['plan_proration_note'] ?? 'Prorrateo sujeto a revision administrativa; no calcula cobros automaticos.') ?></textarea>
                                            <textarea class="form-control form-control-sm mb-2" name="motivo" rows="2" minlength="5" maxlength="500" placeholder="Motivo comercial" required></textarea>
                                            <button class="btn btn-sm btn-primary">Guardar facturacion</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="small text-secondary mt-2">Asigne un plan antes de configurar facturacion.</div>
                                    <?php endif; ?>
                                </details>
                                <details class="mt-2">
                                    <summary class="small">Crear administrador inicial</summary>
                                    <form class="mt-2" action="/superadmin/firmas/<?= (int) $firma['id'] ?>/administrador" method="post" data-ajax-form>
                                        <input class="form-control form-control-sm mb-1" name="nombre" placeholder="Nombre" required>
                                        <input class="form-control form-control-sm mb-1" name="email" type="email" placeholder="Correo" required>
                                        <input class="form-control form-control-sm mb-1" name="password" type="password" minlength="12" placeholder="Contrasena inicial" required>
                                        <input type="hidden" name="tipo" value="interno">
                                        <button class="btn btn-sm btn-primary">Crear administrador</button>
                                    </form>
                                </details>
                                <details class="mt-2">
                                    <summary class="small">Historial comercial</summary>
                                    <?php if ($history === []): ?>
                                        <div class="small text-secondary mt-2">Sin historial comercial registrado.</div>
                                    <?php else: ?>
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm">
                                                <thead><tr><th>Fecha</th><th>Evento</th><th>Plan</th><th>Responsable</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($history as $event): ?>
                                                    <tr>
                                                        <td><?= e($event['created_at'] ?? '') ?></td>
                                                        <td>
                                                            <?= e($event['evento'] ?? '') ?>
                                                            <div class="small text-secondary"><?= e($event['motivo'] ?? '') ?></div>
                                                        </td>
                                                        <td>
                                                            <?= e($event['plan_nombre'] ?? 'Sin plan') ?>
                                                            <?php if (!empty($event['plan_anterior_nombre'])): ?>
                                                                <div class="small text-secondary">Anterior: <?= e($event['plan_anterior_nombre']) ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= e($event['usuario_nombre'] ?? 'No registrado') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($firmas === []): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No hay firmas registradas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
