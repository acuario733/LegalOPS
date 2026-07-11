<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="/booking"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <button class="btn btn-outline-success" id="export-booking-csv" type="button"><i class="bi bi-download me-1"></i>Exportar CSV</button>
</div>
<form class="card card-body border-0 shadow-sm mb-3" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label" for="date-from">Desde</label><input id="date-from" name="fecha_desde" type="date" class="form-control" value="<?= e($filters['fecha_desde'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label" for="booking-status">Estado</label><select id="booking-status" name="estado" class="form-select"><option value="">Todos</option><?php foreach (['confirmada','cancelada','completada'] as $status): ?><option value="<?= $status ?>" <?= ($filters['estado'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><button class="btn btn-primary" type="submit">Aplicar filtros</button></div>
    </div>
</form>
<section class="card shadow-sm border-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="booking-appointments-table">
        <thead class="table-light"><tr><th>Cliente</th><th>Email</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Prospecto</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($appointments as $appointment): ?>
            <tr><td><?= e($appointment['nombre_cliente']) ?></td><td><?= e($appointment['email_cliente']) ?></td><td><?= e($appointment['fecha']) ?></td><td><?= e(substr((string) $appointment['hora_inicio'],0,5)) ?></td><td><?= e(ucfirst($appointment['estado'])) ?></td><td><?php if ($appointment['prospecto_id']): ?><a href="/prospectos/<?= e($appointment['prospecto_id']) ?>">Ver prospecto</a><?php else: ?>—<?php endif; ?></td><td class="text-end"><?php if ($appointment['estado'] === 'confirmada'): ?><button class="btn btn-sm btn-outline-success" type="button" data-complete-appointment="<?= e($appointment['id']) ?>">Marcar como completada</button><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if ($appointments === []): ?><tr><td colspan="7" class="text-center text-secondary py-5">No hay citas para los filtros seleccionados.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></section>
<script>
document.addEventListener('click', async event => {
    const button = event.target.closest('[data-complete-appointment]');
    if (!button) return;
    try {
        await window.LegalOPS.request(`/booking/appointments/${button.dataset.completeAppointment}/complete`, {method:'PATCH'});
        window.location.reload();
    } catch (error) { window.alert(error.message); }
});
document.getElementById('export-booking-csv')?.addEventListener('click', () => {
    const rows = [...document.querySelectorAll('#booking-appointments-table tr')].map(row => [...row.cells].slice(0,6).map(cell => `"${cell.innerText.replaceAll('"','""')}"`).join(','));
    const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob(['\uFEFF'+rows.join('\n')],{type:'text/csv;charset=utf-8'})); link.download='citas.csv'; link.click(); URL.revokeObjectURL(link.href);
});
</script>
