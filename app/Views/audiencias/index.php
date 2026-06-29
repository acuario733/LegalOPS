<?php
declare(strict_types=1);
$estados = ['programada','realizada','cancelada'];
$modalidades = ['presencial','virtual','mixta','telefonica','otra'];
$selectedCaso = (int) ($filters['caso_id'] ?? 0);
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nueva audiencia</h2></div><div class="card-body">
            <form action="/audiencias" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-empty-label="Seleccione" required><option value="">Seleccione</option><?php foreach ($casos as $caso): ?><?php if ($selectedCaso === (int) $caso['id']): ?><option value="<?= (int) $caso['id'] ?>" selected><?= e($caso['titulo']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required></div>
                <div class="row g-2"><div class="col-sm-6"><label class="form-label">Fecha</label><input class="form-control" name="fecha" type="date" value="<?= e(date('Y-m-d')) ?>" required></div><div class="col-sm-6"><label class="form-label">Hora</label><input class="form-control" name="hora" type="time" value="08:00" required></div></div>
                <div class="row g-2 mt-1"><div class="col-sm-6"><label class="form-label">Modalidad</label><select class="form-select" name="modalidad"><?php foreach ($modalidades as $modalidad): ?><option value="<?= e($modalidad) ?>"><?= e($modalidad) ?></option><?php endforeach; ?></select></div><div class="col-sm-6"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id" data-ajax-select data-url="/api/select/usuarios" data-empty-label="Sin asignar"><option value="">Sin asignar</option></select></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Despacho</label><select class="form-select" name="despacho"><option value="">Seleccione</option><?php foreach ($catalogos['despacho'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div>
                <div class="row g-2"><div class="col-sm-6"><label class="form-label">Juez responsable</label><input class="form-control" name="juez_responsable" maxlength="180"></div><div class="col-sm-6"><label class="form-label">Contacto despacho</label><input class="form-control" name="despacho_contacto" maxlength="255"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Lugar</label><input class="form-control" name="lugar"></div>
                <div class="mb-2"><label class="form-label">Enlace</label><input class="form-control" name="enlace" type="url"></div>
                <input type="hidden" name="estado" value="programada">
                <button class="btn btn-primary" type="submit">Crear audiencia</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get" action="/audiencias"><div class="col-md-3"><label class="form-label">Busqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div><div class="col-md-2"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-empty-label="Todos"><option value="">Todos</option><?php foreach ($casos as $caso): ?><?php if ($selectedCaso === (int) $caso['id']): ?><option value="<?= (int) $caso['id'] ?>" selected><?= e($caso['titulo']) ?></option><?php endif; ?><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Desde</label><input class="form-control" name="desde" type="date" value="<?= e($filters['desde'] ?? '') ?>"></div><div class="col-md-1"><label class="form-label">Hasta</label><input class="form-control" name="hasta" type="date" value="<?= e($filters['hasta'] ?? '') ?>"></div><div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div></form></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Agenda</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Audiencia</th><th>Caso</th><th>Fecha</th><th>Modalidad</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($audiencias['items'] as $audiencia): ?><tr data-hearing-state="<?= e($audiencia['estado_visual']) ?>"><td><strong><?= e($audiencia['titulo']) ?></strong><div class="small text-secondary"><?= e($audiencia['responsable_nombre'] ?? 'Sin asignar') ?></div></td><td><?= e($audiencia['caso_titulo']) ?></td><td><?= e($audiencia['fecha']) ?> <?= e(substr((string) $audiencia['hora'], 0, 5)) ?></td><td><?= e($audiencia['modalidad']) ?></td><td><span class="badge text-bg-secondary" data-hearing-badge><?= e($audiencia['estado_visual']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/audiencias/<?= (int) $audiencia['id'] ?>">Ver</a></td></tr><?php endforeach; ?>
        <?php if ($audiencias['items'] === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">No hay audiencias.</td></tr><?php endif; ?>
        </tbody></table></div><div class="card-footer small text-secondary">Total: <?= (int) $audiencias['total'] ?></div></div>
    </div>
</div>
