<?php
declare(strict_types=1);
$estados = ['programada','realizada','cancelada'];
$modalidades = ['presencial','virtual','mixta','telefonica','otra'];
$despachoActual = (string) ($audiencia['despacho'] ?? '');
$hasDespacho = $despachoActual !== '' && in_array($despachoActual, array_column($catalogos['despacho'], 'codigo'), true);
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card"><div class="card-header"><h2 class="card-title"><?= e($audiencia['titulo']) ?></h2></div><div class="card-body">
            <dl class="row mb-0"><dt class="col-sm-4">Caso</dt><dd class="col-sm-8"><a href="/casos/<?= (int) $audiencia['caso_id'] ?>"><?= e($audiencia['caso_titulo']) ?></a></dd><dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e($audiencia['fecha']) ?> <?= e(substr((string) $audiencia['hora'], 0, 5)) ?></dd><dt class="col-sm-4">Timezone</dt><dd class="col-sm-8"><?= e($audiencia['timezone']) ?></dd><dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><span class="badge text-bg-secondary" data-hearing-badge><?= e($audiencia['estado_visual']) ?></span></dd><dt class="col-sm-4">Responsable</dt><dd class="col-sm-8"><?= e($audiencia['responsable_nombre'] ?? 'Sin asignar') ?></dd><dt class="col-sm-4">Despacho</dt><dd class="col-sm-8"><?= e(catalog_label($catalogos['despacho'], $despachoActual)) ?></dd><dt class="col-sm-4">Juez</dt><dd class="col-sm-8"><?= e($audiencia['juez_responsable'] ?? '') ?></dd><dt class="col-sm-4">Contacto</dt><dd class="col-sm-8"><?= e($audiencia['despacho_contacto'] ?? '') ?></dd><dt class="col-sm-4">Lugar</dt><dd class="col-sm-8"><?= e($audiencia['lugar'] ?? '') ?></dd></dl>
            <?php if (($audiencia['enlace'] ?? '') !== ''): ?><a class="btn btn-sm btn-outline-primary mt-3" href="<?= e($audiencia['enlace']) ?>" target="_blank" rel="noopener">Abrir enlace</a><?php endif; ?>
            <?php if (($audiencia['resultado'] ?? '') !== ''): ?><div class="border-top mt-3 pt-3"><strong>Resultado</strong><p class="mb-0"><?= e($audiencia['resultado']) ?></p></div><?php endif; ?>
        </div><div class="card-footer d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="/audiencias">Volver</a><a class="btn btn-outline-secondary" href="/casos/<?= (int) $audiencia['caso_id'] ?>">Regresar al caso</a><button class="btn btn-outline-success" data-action="/audiencias/<?= (int) $audiencia['id'] ?>/resultado" data-prompt="Resultado de la audiencia" data-prompt-field="resultado">Registrar resultado</button></div></div>
    </div>
    <div class="col-xl-7">
        <div class="card"><div class="card-header"><h2 class="card-title">Editar audiencia</h2></div><div class="card-body">
            <form action="/audiencias/<?= (int) $audiencia['id'] ?>" method="post" data-ajax-form>
                <input type="hidden" name="_method" value="PATCH">
                <div class="mb-2"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-empty-label="Seleccione" required><option value="<?= (int) $audiencia['caso_id'] ?>" selected><?= e($audiencia['caso_titulo']) ?></option></select></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" value="<?= e($audiencia['titulo']) ?>" required></div>
                <div class="row g-2"><div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control" name="fecha" type="date" value="<?= e($audiencia['fecha']) ?>"></div><div class="col-md-4"><label class="form-label">Hora</label><input class="form-control" name="hora" type="time" value="<?= e(substr((string) $audiencia['hora'], 0, 5)) ?>"></div><div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado"><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= $audiencia['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div></div>
                <div class="row g-2 mt-1"><div class="col-md-6"><label class="form-label">Modalidad</label><select class="form-select" name="modalidad"><?php foreach ($modalidades as $modalidad): ?><option value="<?= e($modalidad) ?>" <?= $audiencia['modalidad'] === $modalidad ? 'selected' : '' ?>><?= e($modalidad) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id" data-ajax-select data-url="/api/select/usuarios" data-empty-label="Sin asignar"><option value="">Sin asignar</option><?php if (($audiencia['responsable_usuario_id'] ?? null) !== null): ?><option value="<?= (int) $audiencia['responsable_usuario_id'] ?>" selected><?= e($audiencia['responsable_nombre'] ?? ('Usuario #' . $audiencia['responsable_usuario_id'])) ?></option><?php endif; ?></select></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Despacho</label><select class="form-select" name="despacho"><option value="">Seleccione</option><?php if ($despachoActual !== '' && !$hasDespacho): ?><option value="<?= e($despachoActual) ?>" selected><?= e($despachoActual) ?> (no catalogado)</option><?php endif; ?><?php foreach ($catalogos['despacho'] as $item): ?><option value="<?= e($item['codigo']) ?>" <?= $despachoActual === $item['codigo'] ? 'selected' : '' ?>><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div>
                <div class="row g-2"><div class="col-md-6"><label class="form-label">Juez responsable</label><input class="form-control" name="juez_responsable" value="<?= e($audiencia['juez_responsable'] ?? '') ?>" maxlength="180"></div><div class="col-md-6"><label class="form-label">Contacto despacho</label><input class="form-control" name="despacho_contacto" value="<?= e($audiencia['despacho_contacto'] ?? '') ?>" maxlength="255"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Lugar</label><input class="form-control" name="lugar" value="<?= e($audiencia['lugar'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label">Enlace</label><input class="form-control" name="enlace" type="url" value="<?= e($audiencia['enlace'] ?? '') ?>"></div>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </form>
        </div></div>
    </div>
</div>
