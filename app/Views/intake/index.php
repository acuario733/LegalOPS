<?php

declare(strict_types=1);
?>
<section class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
        <div>
            <h2 class="h5 mb-1">Formularios públicos</h2>
            <p class="text-secondary mb-0">Crea enlaces de captación y revisa cada solicitud recibida.</p>
        </div>
        <a class="btn btn-success" href="/intake/nuevo"><i class="bi bi-plus-lg me-1"></i>Nuevo formulario</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>Nombre</th><th>URL pública</th><th>Envíos</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($forms as $form): ?>
                <?php $publicUrl = url('/intake/' . ($currentUser['firma_slug'] ?? '') . '/' . $form['slug']); ?>
                <tr>
                    <td><strong><?= e($form['nombre']) ?></strong><div class="small text-secondary"><?= e($form['titulo']) ?></div></td>
                    <td>
                        <div class="input-group input-group-sm" style="min-width:280px">
                            <input class="form-control" readonly value="<?= e($publicUrl) ?>" aria-label="URL pública">
                            <button class="btn btn-outline-secondary" type="button" data-copy="<?= e($publicUrl) ?>" title="Copiar URL"><i class="bi bi-copy"></i></button>
                        </div>
                    </td>
                    <td><?= e($form['total_envios'] ?? 0) ?></td>
                    <td><span class="badge <?= (int) $form['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $form['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="/intake/<?= e($form['id']) ?>/submissions">Ver envíos</a>
                        <a class="btn btn-sm btn-outline-secondary" href="/intake/<?= e($form['id']) ?>/preview" target="_blank">Vista previa</a>
                        <a class="btn btn-sm btn-outline-secondary" href="/intake/<?= e($form['id']) ?>/editar">Editar</a>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-delete-intake="<?= e($form['id']) ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($forms === []): ?>
                <tr><td colspan="5" class="text-center py-5 text-secondary">Todavía no hay formularios. Crea el primero para comenzar a captar prospectos.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
document.addEventListener('click', async (event) => {
    const copy = event.target.closest('[data-copy]');
    if (copy) {
        await navigator.clipboard.writeText(copy.dataset.copy);
        copy.innerHTML = '<i class="bi bi-check-lg"></i>';
        return;
    }
    const remove = event.target.closest('[data-delete-intake]');
    if (!remove || !window.confirm('¿Eliminar este formulario?')) return;
    try {
        await window.LegalOPS.request(`/intake/${remove.dataset.deleteIntake}`, {method: 'DELETE'});
        window.location.reload();
    } catch (error) {
        window.alert(error.message);
    }
});
</script>
