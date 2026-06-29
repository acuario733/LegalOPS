<?php

declare(strict_types=1);

$activeDays = [];
if ($config !== null) {
    $decodedDays = is_string($config['dias_activos']) ? json_decode($config['dias_activos'], true) : $config['dias_activos'];
    $activeDays = is_array($decodedDays) ? array_map('intval', $decodedDays) : [];
}
$dayLabels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
$publicUrl = $config === null ? '' : url('/booking/' . $config['slug']);
?>
<style>
.booking-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.8fr);gap:1rem;align-items:start}
.booking-surface{background:#fff;border:1px solid #dbe3ec;border-radius:10px;padding:1.25rem}
.day-check{border:1px solid #d8e0e9;border-radius:8px;padding:.65rem .8rem;min-width:72px}
@media(max-width:991.98px){.booking-layout{grid-template-columns:1fr}}
</style>
<?php if ($config !== null): ?>
<div class="booking-surface d-flex flex-wrap align-items-center gap-2 mb-3">
    <strong class="me-2">Enlace público de reserva</strong>
    <input class="form-control flex-grow-1" style="min-width:260px" readonly value="<?= e($publicUrl) ?>" aria-label="Enlace público">
    <button class="btn btn-outline-primary" type="button" data-copy-booking="<?= e($publicUrl) ?>"><i class="bi bi-copy me-1"></i>Copiar</button>
    <button class="btn btn-success" type="button" data-share-booking="<?= e($publicUrl) ?>"><i class="bi bi-share me-1"></i>Compartir</button>
</div>
<?php endif; ?>
<div class="booking-layout">
    <section class="booking-surface">
        <h2 class="h5 mb-3">Configuración del enlace</h2>
        <form id="booking-config-form" data-config-id="<?= e($config['id'] ?? '') ?>">
            <div class="row g-3">
                <?php if ($config === null && count($usuarios) > 1): ?>
                    <div class="col-12"><label class="form-label" for="booking-user">Abogado</label><select id="booking-user" name="usuario_id" class="form-select"><?php foreach ($usuarios as $usuario): ?><option value="<?= e($usuario['id']) ?>"><?= e($usuario['nombre']) ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
                <div class="col-md-6"><label class="form-label" for="booking-title">Título</label><input id="booking-title" name="titulo" class="form-control" maxlength="200" value="<?= e($config['titulo'] ?? 'Consulta legal') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="booking-description">Descripción</label><textarea id="booking-description" name="descripcion" class="form-control" rows="2"><?= e($config['descripcion'] ?? '') ?></textarea></div>
                <div class="col-md-4"><label class="form-label" for="booking-duration">Duración</label><select id="booking-duration" name="duracion_minutos" class="form-select"><?php foreach ([15,30,45,60] as $minutes): ?><option value="<?= $minutes ?>" <?= (int) ($config['duracion_minutos'] ?? 30) === $minutes ? 'selected' : '' ?>><?= $minutes ?> min</option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="booking-start">Hora de inicio</label><input id="booking-start" name="hora_inicio" type="time" class="form-control" value="<?= e(substr((string) ($config['hora_inicio'] ?? '09:00'), 0, 5)) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="booking-end">Hora de fin</label><input id="booking-end" name="hora_fin" type="time" class="form-control" value="<?= e(substr((string) ($config['hora_fin'] ?? '18:00'), 0, 5)) ?>" required></div>
                <div class="col-12"><label class="form-label d-block">Días activos</label><div class="d-flex flex-wrap gap-2"><?php foreach ($dayLabels as $day => $label): ?><label class="day-check"><input class="form-check-input me-1" type="checkbox" name="dias_activos[]" value="<?= $day ?>" <?= in_array($day, $activeDays ?: [1,2,3,4,5], true) ? 'checked' : '' ?>><?= e($label) ?></label><?php endforeach; ?></div></div>
                <div class="col-md-4"><label class="form-label" for="booking-buffer">Buffer entre citas</label><select id="booking-buffer" name="buffer_entre_citas" class="form-select"><?php foreach ([0,5,10,15] as $minutes): ?><option value="<?= $minutes ?>" <?= (int) ($config['buffer_entre_citas'] ?? 0) === $minutes ? 'selected' : '' ?>><?= $minutes ?> min</option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="booking-min">Anticipación mínima</label><input id="booking-min" name="dias_anticipacion_min" type="number" min="0" max="365" class="form-control" value="<?= e($config['dias_anticipacion_min'] ?? 1) ?>"></div>
                <div class="col-md-4"><label class="form-label" for="booking-max">Anticipación máxima</label><input id="booking-max" name="dias_anticipacion_max" type="number" min="1" max="365" class="form-control" value="<?= e($config['dias_anticipacion_max'] ?? 30) ?>"></div>
                <div class="col-md-8"><label class="form-label" for="booking-notify">Email de notificación</label><input id="booking-notify" name="notificar_email" type="email" class="form-control" value="<?= e($config['notificar_email'] ?? '') ?>"></div>
                <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2"><input id="booking-active" name="activo" class="form-check-input" type="checkbox" value="1" <?= (int) ($config['activo'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="booking-active">Enlace activo</label></div></div>
            </div>
            <button class="btn btn-success mt-4" type="submit"><i class="bi bi-floppy me-1"></i>Guardar configuración</button>
            <?php if ($config !== null): ?><button class="btn btn-outline-danger mt-4 ms-2" type="button" id="delete-booking">Eliminar enlace</button><?php endif; ?>
        </form>
    </section>
    <aside class="booking-surface">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Próximas citas</h2><?php if ($config !== null): ?><a href="/booking/<?= e($config['id']) ?>/appointments">Ver todas</a><?php endif; ?></div>
        <div class="list-group list-group-flush">
            <?php foreach (array_slice($appointments, 0, 6) as $appointment): ?>
                <div class="list-group-item px-0"><strong><?= e($appointment['nombre_cliente']) ?></strong><div class="small text-secondary"><?= e($appointment['fecha']) ?> · <?= e(substr((string) $appointment['hora_inicio'], 0, 5)) ?> · <?= e(ucfirst($appointment['estado'])) ?></div></div>
            <?php endforeach; ?>
            <?php if ($appointments === []): ?><p class="text-secondary">No hay citas próximas.</p><?php endif; ?>
        </div>
    </aside>
</div>
<script>
const bookingForm = document.getElementById('booking-config-form');
bookingForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(bookingForm).entries());
    data.dias_activos = [...bookingForm.querySelectorAll('[name="dias_activos[]"]:checked')].map(input => Number(input.value));
    data.activo = document.getElementById('booking-active').checked;
    const id = bookingForm.dataset.configId;
    try {
        await window.LegalOPS.request(id ? `/booking/${id}` : '/booking', {method: id ? 'PATCH' : 'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
        window.location.reload();
    } catch (error) { window.alert(error.message); }
});
document.querySelector('[data-copy-booking]')?.addEventListener('click', event => navigator.clipboard.writeText(event.currentTarget.dataset.copyBooking));
document.querySelector('[data-share-booking]')?.addEventListener('click', event => navigator.share ? navigator.share({title:'Reserva una consulta',url:event.currentTarget.dataset.shareBooking}) : navigator.clipboard.writeText(event.currentTarget.dataset.shareBooking));
document.getElementById('delete-booking')?.addEventListener('click', async () => {
    if (!confirm('¿Eliminar este enlace de reserva?')) return;
    await window.LegalOPS.request(`/booking/${bookingForm.dataset.configId}`, {method:'DELETE'});
    window.location.reload();
});
</script>
