<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <a href="/intake" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <button type="button" class="btn btn-outline-success" id="export-intake-csv"><i class="bi bi-download me-1"></i>Exportar CSV</button>
</div>
<section class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="intake-submissions-table">
            <thead class="table-light"><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Prospecto</th></tr></thead>
            <tbody>
            <?php foreach ($submissions as $submission): ?>
                <?php $data = $submission['datos']; ?>
                <tr>
                    <td><?= e($submission['created_at']) ?></td>
                    <td><?= e($submission['prospecto_nombre'] ?? $data['nombre'] ?? '') ?></td>
                    <td><?= e($submission['prospecto_email'] ?? $data['email'] ?? '') ?></td>
                    <td><?= e($submission['prospecto_telefono'] ?? $data['telefono'] ?? '') ?></td>
                    <td><?php if ($submission['prospecto_id'] !== null): ?><a href="/prospectos/<?= e($submission['prospecto_id']) ?>">Ver prospecto #<?= e($submission['prospecto_id']) ?></a><?php else: ?><span class="text-secondary">Pendiente</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($submissions === []): ?><tr><td colspan="5" class="text-center py-5 text-secondary">No hay envíos registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
document.getElementById('export-intake-csv')?.addEventListener('click', () => {
    const rows = [...document.querySelectorAll('#intake-submissions-table tr')].map(row =>
        [...row.cells].map(cell => `"${cell.innerText.replaceAll('"', '""')}"`).join(',')
    );
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob(['\uFEFF' + rows.join('\n')], {type: 'text/csv;charset=utf-8'}));
    link.download = 'envios-intake.csv';
    link.click();
    URL.revokeObjectURL(link.href);
});
</script>
