<?php
declare(strict_types=1);
$estados = ['activo','cerrado','archivado'];
$prioridades = ['baja','media','alta','critica'];
$tipoActual = (string) ($caso['tipo_proceso'] ?? '');
$jurisdiccionActual = (string) ($caso['jurisdiccion'] ?? '');
$despachoActual = (string) ($caso['despacho'] ?? '');
$hasCatalogCode = static fn (array $items, string $code): bool => $code !== '' && in_array($code, array_column($items, 'codigo'), true);
$permissions = is_array($currentUser['permissions'] ?? null) ? $currentUser['permissions'] : [];
$can = static fn (string $permission): bool => in_array('*', $permissions, true) || in_array($permission, $permissions, true);
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card"><div class="card-header"><h2 class="card-title"><?= e($caso['titulo']) ?></h2></div><div class="card-body">
            <dl class="row mb-0"><dt class="col-sm-4">Cliente</dt><dd class="col-sm-8"><a href="/clientes/<?= (int) $caso['cliente_id'] ?>"><?= e($caso['cliente_nombre']) ?></a></dd><dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><span class="badge text-bg-secondary"><?= e($caso['estado']) ?></span></dd><dt class="col-sm-4">Prioridad</dt><dd class="col-sm-8"><?= e($caso['prioridad']) ?></dd><dt class="col-sm-4">Radicado</dt><dd class="col-sm-8"><?= e($caso['radicado'] ?? '') ?></dd><dt class="col-sm-4">Responsable</dt><dd class="col-sm-8"><?= e($caso['responsable_nombre'] ?? 'Sin asignar') ?></dd><dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= e(catalog_label($catalogos['tipo_caso'], $tipoActual)) ?></dd><dt class="col-sm-4">Jurisdiccion</dt><dd class="col-sm-8"><?= e(catalog_label($catalogos['jurisdiccion'], $jurisdiccionActual)) ?></dd><dt class="col-sm-4">Despacho</dt><dd class="col-sm-8"><?= e(catalog_label($catalogos['despacho'], $despachoActual)) ?></dd></dl>
        </div><div class="card-footer d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="/casos">Volver</a><?php if ($caso['estado'] !== 'cerrado'): ?><button class="btn btn-outline-warning" data-action="/casos/<?= (int) $caso['id'] ?>/cerrar" data-prompt="Motivo de cierre" data-prompt-field="motivo">Cerrar</button><?php endif; ?><?php if ($caso['estado'] !== 'archivado'): ?><button class="btn btn-outline-danger" data-action="/casos/<?= (int) $caso['id'] ?>/archivar" data-prompt="Motivo de archivo" data-prompt-field="motivo">Archivar</button><?php endif; ?><?php if (in_array($caso['estado'], ['cerrado','archivado'], true)): ?><button class="btn btn-outline-success" data-action="/casos/<?= (int) $caso['id'] ?>/reabrir" data-prompt="Motivo de reapertura" data-prompt-field="motivo">Reabrir</button><?php endif; ?></div></div>
        <div class="card mt-3"><div class="card-header"><h2 class="card-title">Ficha 360</h2></div><div class="list-group list-group-flush"><a class="list-group-item list-group-item-action" href="/casos/<?= (int) $caso['id'] ?>/partes">Partes procesales</a><a class="list-group-item list-group-item-action" href="/casos/<?= (int) $caso['id'] ?>/timeline">Linea de tiempo</a><a class="list-group-item list-group-item-action" href="/terminos?caso_id=<?= (int) $caso['id'] ?>">Terminos</a><a class="list-group-item list-group-item-action" href="/audiencias?caso_id=<?= (int) $caso['id'] ?>">Audiencias</a><a class="list-group-item list-group-item-action" href="/tareas?caso_id=<?= (int) $caso['id'] ?>">Tareas</a></div></div>
    </div>
    <div class="col-xl-7">
        <div class="card"><div class="card-header"><h2 class="card-title">Editar caso</h2></div><div class="card-body">
            <form action="/casos/<?= (int) $caso['id'] ?>" method="post" data-ajax-form>
                <input type="hidden" name="_method" value="PATCH">
                <div class="mb-2"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Seleccione" required><option value="<?= (int) $caso['cliente_id'] ?>" selected><?= e($caso['cliente_nombre']) ?></option></select></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" value="<?= e($caso['titulo']) ?>" required></div>
                <div class="row g-2"><div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado"><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= $caso['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><?php foreach ($prioridades as $prioridad): ?><option value="<?= e($prioridad) ?>" <?= $caso['prioridad'] === $prioridad ? 'selected' : '' ?>><?= e($prioridad) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Radicado</label><input class="form-control" name="radicado" value="<?= e($caso['radicado'] ?? '') ?>"></div></div>
                <div class="row g-2 mt-1"><div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="tipo_proceso"><option value="">Seleccione</option><?php if ($tipoActual !== '' && !$hasCatalogCode($catalogos['tipo_caso'], $tipoActual)): ?><option value="<?= e($tipoActual) ?>" selected><?= e($tipoActual) ?> (no catalogado)</option><?php endif; ?><?php foreach ($catalogos['tipo_caso'] as $item): ?><option value="<?= e($item['codigo']) ?>" <?= $tipoActual === $item['codigo'] ? 'selected' : '' ?>><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Jurisdiccion</label><select class="form-select" name="jurisdiccion"><option value="">Seleccione</option><?php if ($jurisdiccionActual !== '' && !$hasCatalogCode($catalogos['jurisdiccion'], $jurisdiccionActual)): ?><option value="<?= e($jurisdiccionActual) ?>" selected><?= e($jurisdiccionActual) ?> (no catalogada)</option><?php endif; ?><?php foreach ($catalogos['jurisdiccion'] as $item): ?><option value="<?= e($item['codigo']) ?>" <?= $jurisdiccionActual === $item['codigo'] ? 'selected' : '' ?>><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Apertura</label><input class="form-control" name="fecha_apertura" type="date" value="<?= e($caso['fecha_apertura'] ?? '') ?>"></div></div>
                <div class="row g-2 mt-1"><div class="col-md-6"><label class="form-label">Despacho</label><select class="form-select" name="despacho"><option value="">Seleccione</option><?php if ($despachoActual !== '' && !$hasCatalogCode($catalogos['despacho'], $despachoActual)): ?><option value="<?= e($despachoActual) ?>" selected><?= e($despachoActual) ?> (no catalogado)</option><?php endif; ?><?php foreach ($catalogos['despacho'] as $item): ?><option value="<?= e($item['codigo']) ?>" <?= $despachoActual === $item['codigo'] ? 'selected' : '' ?>><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id" data-ajax-select data-url="/api/select/usuarios" data-empty-label="Sin asignar"><option value="">Sin asignar</option><?php if (($caso['responsable_usuario_id'] ?? null) !== null): ?><option value="<?= (int) $caso['responsable_usuario_id'] ?>" selected><?= e($caso['responsable_nombre'] ?? ('Usuario #' . $caso['responsable_usuario_id'])) ?></option><?php endif; ?></select></div></div>
                <div class="mb-3 mt-2"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" rows="5"><?= e($caso['descripcion'] ?? '') ?></textarea></div>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </form>
        </div></div>
    </div>
</div>
<div class="card mt-4" data-caso-workspace data-caso-id="<?= (int) $caso['id'] ?>">
    <div class="card-header p-0">
        <ul class="nav nav-tabs card-header-tabs mx-2 mt-2" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="caso-documentos-tab" data-bs-toggle="tab" data-bs-target="#caso-documentos-panel" type="button" role="tab">Documentos</button></li>
            <?php if ($can('comunicaciones.ver')): ?><li class="nav-item" role="presentation"><button class="nav-link" id="caso-comunicaciones-tab" data-bs-toggle="tab" data-bs-target="#caso-comunicaciones-panel" type="button" role="tab">Comunicaciones</button></li><?php endif; ?>
        </ul>
    </div>
    <div class="card-body tab-content">
        <section class="tab-pane fade show active" id="caso-documentos-panel" role="tabpanel" aria-labelledby="caso-documentos-tab">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h5 mb-1">Documentos del caso</h2>
                    <p class="text-secondary mb-0">Genera documentos desde plantillas o revisa el modulo documental.</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($can('plantillas.usar')): ?><button class="btn btn-primary" type="button" data-open-generate-template="<?= (int) $caso['id'] ?>"><i class="bi bi-file-earmark-plus me-1"></i>Generar desde plantilla</button><?php endif; ?>
                    <a class="btn btn-outline-secondary" href="/documentos?caso_id=<?= (int) $caso['id'] ?>">Ver documentos</a>
                </div>
            </div>
        </section>
        <?php if ($can('comunicaciones.ver')): ?>
            <section class="tab-pane fade" id="caso-comunicaciones-panel" role="tabpanel" aria-labelledby="caso-comunicaciones-tab">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Centro de comunicaciones</h2>
                        <div class="small text-secondary">Pon este email en CCO en tus correos con el cliente. Quedaran registrados aqui automaticamente.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge text-bg-light border px-3 py-2" data-comunicaciones-email>Cargando email BCC...</span>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-copy-comunicaciones-email><i class="bi bi-copy me-1"></i>Copiar</button>
                        <?php if ($can('comunicaciones.crear')): ?><button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#comunicacionModal"><i class="bi bi-plus-lg me-1"></i>Registrar comunicacion</button><?php endif; ?>
                    </div>
                </div>
                <div class="btn-group flex-wrap mb-3" role="group" aria-label="Filtros de comunicaciones">
                    <?php foreach (['' => 'Todos', 'email' => 'Email', 'llamada' => 'Llamada', 'mensaje' => 'Mensaje', 'reunion' => 'Reunion', 'otro' => 'Otro'] as $tipo => $label): ?>
                        <button class="btn btn-outline-primary <?= $tipo === '' ? 'active' : '' ?>" type="button" data-comunicaciones-filter="<?= e($tipo) ?>"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
                <div data-comunicaciones-timeline class="position-relative">
                    <div class="text-secondary py-4">Activa el tab para cargar comunicaciones...</div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../comunicaciones/_form_modal.php'; ?>
<?php include __DIR__ . '/../plantillas/_generate_modal.php'; ?>
<script src="/assets/js/comunicaciones.js"></script>
<script src="/assets/js/template-generate.js"></script>
