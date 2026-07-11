<?php

declare(strict_types=1);

$activeDays = [];
if ($config !== null) {
    $decodedDays = is_string($config['dias_activos']) ? json_decode($config['dias_activos'], true) : $config['dias_activos'];
    $activeDays  = is_array($decodedDays) ? array_map('intval', $decodedDays) : [];
}
$dayLabels  = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$publicUrl  = $config === null ? '' : url('/booking/' . $config['slug']);
$duraciones = [15, 30, 45, 60];
$bufferOpts = [0, 5, 10, 15];
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <h1 class="page-title">Reserva de citas</h1>
        <p class="page-subtitle">Configura tu enlace público de reserva y gestiona tus citas</p>
    </div>
    <?php if ($config !== null): ?>
    <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
        <i class="bi bi-box-arrow-up-right"></i>Ver enlace público
    </a>
    <?php endif; ?>
</div>

<!-- BARRA: ENLACE PÚBLICO -->
<?php if ($config !== null): ?>
<div class="card mb-4">
    <div class="card-body">
        <p class="mb-2" style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-secondary);">
            Enlace público de reserva
        </p>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <input class="form-control flex-grow-1"
                   style="min-width:260px;font-size:var(--font-size-sm);color:var(--color-text-secondary);"
                   readonly
                   value="<?= e($publicUrl) ?>"
                   aria-label="Enlace público de reserva"
                   id="booking-url-input">
            <button class="btn btn-outline-primary" type="button" data-copy-booking="<?= e($publicUrl) ?>">
                <i class="bi bi-copy me-1"></i>Copiar
            </button>
            <button class="btn btn-primary" type="button" data-share-booking="<?= e($publicUrl) ?>">
                <i class="bi bi-share me-1"></i>Compartir
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- LAYOUT 2 COLUMNAS -->
<div class="booking-admin-layout">

    <!-- COL IZQUIERDA: CONFIGURACIÓN -->
    <section>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-gear me-2" style="color:var(--color-green);"></i>
                Configuración del enlace de reserva
            </div>
            <div class="card-body">
                <form id="booking-config-form" data-config-id="<?= e($config['id'] ?? '') ?>">

                    <!-- Abogado (solo si hay múltiples y no hay config) -->
                    <?php if ($config === null && count($usuarios ?? []) > 1): ?>
                    <div class="mb-4">
                        <label class="form-label" for="booking-user">Abogado</label>
                        <select id="booking-user" name="usuario_id" class="form-select">
                            <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= e($usuario['id']) ?>"><?= e($usuario['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Título y descripción -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="booking-title">Título</label>
                            <input id="booking-title" name="titulo" class="form-control"
                                   maxlength="200" required
                                   value="<?= e($config['titulo'] ?? 'Consulta legal') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="booking-description">Descripción</label>
                            <textarea id="booking-description" name="descripcion" class="form-control" rows="2"><?= e($config['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Duración: botones toggle -->
                    <div class="mb-4">
                        <label class="form-label d-block">Duración de la cita</label>
                        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Duración de la cita">
                            <?php $duracionActual = (int)($config['duracion_minutos'] ?? 30); ?>
                            <?php foreach ($duraciones as $min): ?>
                            <label class="duration-btn <?= $duracionActual === $min ? 'active' : '' ?>">
                                <input type="radio" name="duracion_minutos" value="<?= $min ?>"
                                       <?= $duracionActual === $min ? 'checked' : '' ?> hidden>
                                <?= $min ?> min
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Horario -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="booking-start">Hora de inicio</label>
                            <input id="booking-start" name="hora_inicio" type="time" class="form-control"
                                   value="<?= e(substr((string)($config['hora_inicio'] ?? '09:00'), 0, 5)) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="booking-end">Hora de fin</label>
                            <input id="booking-end" name="hora_fin" type="time" class="form-control"
                                   value="<?= e(substr((string)($config['hora_fin'] ?? '18:00'), 0, 5)) ?>" required>
                        </div>
                    </div>

                    <!-- Días activos -->
                    <div class="mb-4">
                        <label class="form-label d-block">Días activos</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($dayLabels as $day => $label): ?>
                            <label class="day-toggle <?= in_array($day, $activeDays ?: [1,2,3,4,5], true) ? 'active' : '' ?>">
                                <input type="checkbox" name="dias_activos[]" value="<?= $day ?>"
                                       <?= in_array($day, $activeDays ?: [1,2,3,4,5], true) ? 'checked' : '' ?> hidden>
                                <?= e($label) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Buffer y anticipación -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <label class="form-label" for="booking-buffer">Buffer entre citas</label>
                            <select id="booking-buffer" name="buffer_entre_citas" class="form-select">
                                <?php foreach ($bufferOpts as $min): ?>
                                <option value="<?= $min ?>" <?= (int)($config['buffer_entre_citas'] ?? 0) === $min ? 'selected' : '' ?>>
                                    <?= $min === 0 ? 'Sin buffer' : "{$min} min" ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="booking-min">Anticipación mínima <small class="text-muted">(días)</small></label>
                            <input id="booking-min" name="dias_anticipacion_min" type="number" min="0" max="365"
                                   class="form-control" value="<?= e($config['dias_anticipacion_min'] ?? 1) ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label" for="booking-max">Anticipación máxima <small class="text-muted">(días)</small></label>
                            <input id="booking-max" name="dias_anticipacion_max" type="number" min="1" max="365"
                                   class="form-control" value="<?= e($config['dias_anticipacion_max'] ?? 30) ?>">
                        </div>
                    </div>

                    <!-- Email de notificación + Activo -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label" for="booking-notify">Email de notificación</label>
                            <input id="booking-notify" name="notificar_email" type="email" class="form-control"
                                   placeholder="notificaciones@firma.com"
                                   value="<?= e($config['notificar_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check form-switch mb-0">
                                <input id="booking-active" name="activo" class="form-check-input toggle-switch"
                                       type="checkbox" value="1" role="switch"
                                       <?= (int)($config['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="booking-active" style="font-size:var(--font-size-sm);">
                                    Enlace activo
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="d-flex gap-2 flex-wrap pt-2" style="border-top:1px solid var(--color-border);">
                        <button class="btn btn-primary" type="submit" id="btn-save-booking">
                            <i class="bi bi-floppy me-1"></i>Guardar configuración
                        </button>
                        <?php if ($config !== null): ?>
                        <button class="btn btn-ghost" type="button" id="delete-booking"
                                style="color:var(--color-danger);">
                            <i class="bi bi-trash me-1"></i>Eliminar enlace
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- COL DERECHA: PRÓXIMAS CITAS -->
    <aside>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar3 me-2" style="color:var(--color-green);"></i>Próximas citas</span>
                <?php if ($config !== null): ?>
                <a href="/booking/<?= e($config['id']) ?>/appointments"
                   style="font-size:var(--font-size-xs);color:var(--color-green);">
                    Ver todas
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($appointments)): ?>
                <div class="text-center py-5" style="color:var(--color-text-muted);">
                    <i class="bi bi-calendar-x" style="font-size:2rem;opacity:0.4;"></i>
                    <p class="mt-2 mb-0" style="font-size:var(--font-size-sm);">No hay citas próximas</p>
                </div>
                <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach (array_slice($appointments, 0, 6) as $apt): ?>
                    <?php
                    $estadoBadge = match ($apt['estado'] ?? 'pendiente') {
                        'confirmada'   => 'badge-confirmada',
                        'cancelada'    => 'badge-cancelada',
                        'reprogramada' => 'badge-reprogramada',
                        default        => 'badge-pendiente',
                    };
                    ?>
                    <li style="padding:12px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:12px;">
                        <div style="width:34px;height:34px;border-radius:var(--radius-full);background:var(--color-green-light);color:var(--color-green);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(mb_substr((string)($apt['nombre_cliente'] ?? 'C'), 0, 1)) ?>
                        </div>
                        <div style="flex:1;overflow:hidden;">
                            <div style="font-size:var(--font-size-sm);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--color-navy);">
                                <?= e($apt['nombre_cliente'] ?? '') ?>
                            </div>
                            <div style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
                                <?= e($apt['fecha'] ?? '') ?> · <?= e(substr((string)($apt['hora_inicio'] ?? ''), 0, 5)) ?>
                            </div>
                        </div>
                        <span class="badge-status <?= $estadoBadge ?>">
                            <?= ucfirst(e($apt['estado'] ?? 'pendiente')) ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<!-- TABLA: TODAS LAS CITAS -->
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-table me-2" style="color:var(--color-green);"></i>Todas las citas</span>
        <div class="d-flex gap-2 flex-wrap">
            <select id="filter-estado" class="form-select form-select-sm" style="width:auto;">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="confirmada">Confirmada</option>
                <option value="cancelada">Cancelada</option>
                <option value="reprogramada">Reprogramada</option>
            </select>
            <input id="filter-fecha" type="date" class="form-control form-control-sm" style="width:auto;"
                   title="Filtrar por fecha">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="appointments-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Estado</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5" style="color:var(--color-text-muted);">
                            <i class="bi bi-calendar" style="font-size:2rem;opacity:0.35;"></i>
                            <p class="mt-2 mb-0" style="font-size:var(--font-size-sm);">Sin citas registradas</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                    <?php
                    $estadoBadgeTbl = match ($apt['estado'] ?? 'pendiente') {
                        'confirmada'   => 'badge-confirmada',
                        'cancelada'    => 'badge-cancelada',
                        'reprogramada' => 'badge-reprogramada',
                        default        => 'badge-pendiente',
                    };
                    ?>
                    <tr data-estado="<?= e($apt['estado'] ?? '') ?>" data-fecha="<?= e($apt['fecha'] ?? '') ?>">
                        <td style="font-weight:500;font-size:var(--font-size-sm);"><?= e($apt['nombre_cliente'] ?? '') ?></td>
                        <td style="font-size:var(--font-size-sm);color:var(--color-text-secondary);"><?= e($apt['fecha'] ?? '') ?></td>
                        <td style="font-size:var(--font-size-sm);color:var(--color-text-secondary);white-space:nowrap;">
                            <?= e(substr((string)($apt['hora_inicio'] ?? ''), 0, 5)) ?>
                            <?php if (!empty($apt['hora_fin'])): ?> – <?= e(substr((string)$apt['hora_fin'], 0, 5)) ?><?php endif; ?>
                        </td>
                        <td><span class="badge-status <?= $estadoBadgeTbl ?>"><?= ucfirst(e($apt['estado'] ?? 'pendiente')) ?></span></td>
                        <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);"><?= e($apt['email_cliente'] ?? '') ?></td>
                        <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);"><?= e($apt['telefono_cliente'] ?? '—') ?></td>
                        <td>
                            <?php if (($apt['estado'] ?? '') !== 'cancelada'): ?>
                            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--color-danger);"
                                    data-cancel-apt="<?= e($apt['id'] ?? '') ?>" title="Cancelar cita">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.booking-admin-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(280px, .8fr);
    gap: 1.5rem;
    align-items: start;
}

.duration-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 18px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: border-color .15s, background .15s, color .15s;
    user-select: none;
}
.duration-btn:hover { border-color: var(--color-green); color: var(--color-green); }
.duration-btn.active {
    border-color: var(--color-green);
    background: var(--color-green);
    color: #fff;
}

.day-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 52px;
    padding: 6px 10px;
    border: 1.5px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--color-text-secondary);
    cursor: pointer;
    transition: border-color .15s, background .15s, color .15s;
    user-select: none;
}
.day-toggle:hover { border-color: var(--color-green); color: var(--color-green); }
.day-toggle.active {
    border-color: var(--color-green);
    background: var(--color-green-light);
    color: var(--color-green);
    font-weight: 600;
}

@media (max-width: 991.98px) {
    .booking-admin-layout {
        display: block;
    }
    .booking-admin-layout > aside {
        margin-top: 1.5rem;
    }
}
</style>

<script>
(function () {
    // ── Duración toggle ────────────────────────────────────────────────────
    document.querySelectorAll('.duration-btn').forEach(label => {
        label.addEventListener('click', () => {
            document.querySelectorAll('.duration-btn').forEach(l => l.classList.remove('active'));
            label.classList.add('active');
        });
    });

    // ── Días activos toggle ────────────────────────────────────────────────
    document.querySelectorAll('.day-toggle').forEach(label => {
        label.addEventListener('click', () => {
            label.classList.toggle('active');
            const cb = label.querySelector('input[type=checkbox]');
            if (cb) cb.checked = label.classList.contains('active');
        });
    });

    // ── Guardar configuración ──────────────────────────────────────────────
    const form = document.getElementById('booking-config-form');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btn-save-booking');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando…'; }

        const data        = Object.fromEntries(new FormData(form).entries());
        data.dias_activos = [...form.querySelectorAll('[name="dias_activos[]"]:checked')].map(i => Number(i.value));
        data.activo       = document.getElementById('booking-active')?.checked ?? true;
        const id          = form.dataset.configId;

        try {
            await window.LegalOPS.request(id ? `/booking/${id}` : '/booking', {
                method:  id ? 'PATCH' : 'POST',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify(data),
            });
            if (window.LegalUI) window.LegalUI.toast('Configuración guardada', 'success');
            else window.location.reload();
        } catch (err) {
            if (window.LegalUI) window.LegalUI.toast(err.message || 'Error al guardar', 'error');
            else window.alert(err.message);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar configuración'; }
        }
    });

    // ── Copiar enlace ──────────────────────────────────────────────────────
    document.querySelector('[data-copy-booking]')?.addEventListener('click', (e) => {
        const url = e.currentTarget.dataset.copyBooking;
        if (window.LegalUI) window.LegalUI.copyText(url, e.currentTarget);
        else navigator.clipboard.writeText(url);
    });

    // ── Compartir enlace ───────────────────────────────────────────────────
    document.querySelector('[data-share-booking]')?.addEventListener('click', (e) => {
        const url = e.currentTarget.dataset.shareBooking;
        if (navigator.share) {
            navigator.share({ title: 'Reserva una consulta', url });
        } else if (window.LegalUI) {
            window.LegalUI.copyText(url, e.currentTarget);
        } else {
            navigator.clipboard.writeText(url);
        }
    });

    // ── Eliminar enlace ────────────────────────────────────────────────────
    document.getElementById('delete-booking')?.addEventListener('click', () => {
        const id = form?.dataset.configId;
        if (!id) return;
        const doDelete = async () => {
            await window.LegalOPS.request(`/booking/${id}`, { method: 'DELETE' });
            window.location.reload();
        };
        if (window.LegalUI) {
            window.LegalUI.confirm('¿Eliminar este enlace de reserva? Esta acción no se puede deshacer.', doDelete, {
                confirmLabel: 'Eliminar',
                confirmClass: 'btn-danger',
            });
        } else if (confirm('¿Eliminar este enlace de reserva?')) {
            doDelete();
        }
    });

    // ── Cancelar cita ──────────────────────────────────────────────────────
    document.querySelectorAll('[data-cancel-apt]').forEach(btn => {
        btn.addEventListener('click', () => {
            const aptId    = btn.dataset.cancelApt;
            const doCancel = async () => {
                await window.LegalOPS.request(`/booking/appointments/${aptId}/cancel`, { method: 'PATCH' });
                window.location.reload();
            };
            if (window.LegalUI) {
                window.LegalUI.confirm('¿Cancelar esta cita?', doCancel, {
                    confirmLabel: 'Sí, cancelar',
                    confirmClass: 'btn-danger',
                });
            } else if (confirm('¿Cancelar esta cita?')) {
                doCancel();
            }
        });
    });

    // ── Filtros de tabla ───────────────────────────────────────────────────
    const filterEstado = document.getElementById('filter-estado');
    const filterFecha  = document.getElementById('filter-fecha');

    function applyFilters() {
        const estado = filterEstado?.value || '';
        const fecha  = filterFecha?.value  || '';
        document.querySelectorAll('#appointments-table tbody tr[data-estado]').forEach(row => {
            const matchEstado = !estado || row.dataset.estado === estado;
            const matchFecha  = !fecha  || row.dataset.fecha  === fecha;
            row.style.display = matchEstado && matchFecha ? '' : 'none';
        });
    }

    filterEstado?.addEventListener('change', applyFilters);
    filterFecha?.addEventListener('input',   applyFilters);
}());
</script>
