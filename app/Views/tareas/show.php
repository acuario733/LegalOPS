<?php
declare(strict_types=1);
$estados = ['pendiente','en_proceso','completada','vencida','cancelada'];
$prioridades = ['baja','media','alta','critica'];
$today = date('Y-m-d');
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card"><div class="card-header"><h2 class="card-title"><?= e($tarea['titulo']) ?></h2></div><div class="card-body">
            <dl class="row mb-0"><dt class="col-sm-4">Caso</dt><dd class="col-sm-8"><?= $tarea['caso_id'] ? '<a href="/casos/' . (int) $tarea['caso_id'] . '">' . e($tarea['caso_titulo'] ?? '') . '</a>' : 'Sin caso' ?></dd><dt class="col-sm-4">Termino</dt><dd class="col-sm-8"><?= $tarea['termino_id'] ? '<a href="/terminos/' . (int) $tarea['termino_id'] . '">' . e($tarea['termino_titulo'] ?? '') . '</a>' : 'Sin termino' ?></dd><dt class="col-sm-4">Vence</dt><dd class="col-sm-8"><?= e($tarea['fecha_vencimiento'] ?? '') ?></dd><dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><span class="badge text-bg-secondary" data-task-badge><?= e($tarea['estado_visual']) ?></span></dd><dt class="col-sm-4">Responsable</dt><dd class="col-sm-8"><?= e($tarea['responsable_nombre'] ?? 'Sin asignar') ?></dd><dt class="col-sm-4">Completada</dt><dd class="col-sm-8"><?= e($tarea['completed_at'] ?? '') ?></dd></dl>
        </div><div class="card-footer d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="/tareas">Volver</a><?php if ($tarea['caso_id']): ?><a class="btn btn-outline-secondary" href="/casos/<?= (int) $tarea['caso_id'] ?>">Regresar al caso</a><?php endif; ?></div></div>
        <div class="card mt-3"><div class="card-header"><h2 class="card-title">Gestion</h2></div><div class="card-body">
            <form action="/tareas/<?= (int) $tarea['id'] ?>/estado" method="post" data-ajax-form class="mb-3"><label class="form-label">Cambiar estado</label><div class="input-group"><select class="form-select" name="estado"><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= $tarea['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary">Actualizar</button></div></form>
            <form action="/tareas/<?= (int) $tarea['id'] ?>/reasignar" method="post" data-ajax-form><label class="form-label">Reasignar</label><select class="form-select" name="responsable_usuario_id" data-ajax-select data-url="/api/select/usuarios" data-empty-label="Seleccione" required><option value="">Seleccione</option><?php if (($tarea['responsable_usuario_id'] ?? null) !== null): ?><option value="<?= (int) $tarea['responsable_usuario_id'] ?>" selected><?= e($tarea['responsable_nombre'] ?? ('Usuario #' . $tarea['responsable_usuario_id'])) ?></option><?php endif; ?></select><button class="btn btn-outline-primary mt-2">Asignar</button></form>
        </div></div>
    </div>
    <div class="col-xl-7">
        <div class="card"><div class="card-header"><h2 class="card-title">Editar tarea</h2></div><div class="card-body">
            <form action="/tareas/<?= (int) $tarea['id'] ?>" method="post" data-ajax-form>
                <input type="hidden" name="_method" value="PATCH">
                <div class="row g-2"><div class="col-md-6"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-empty-label="Sin caso"><option value="">Sin caso</option><?php if (($tarea['caso_id'] ?? null) !== null): ?><option value="<?= (int) $tarea['caso_id'] ?>" selected><?= e($tarea['caso_titulo'] ?? ('Caso #' . $tarea['caso_id'])) ?></option><?php endif; ?></select></div><div class="col-md-6"><label class="form-label">Termino</label><select class="form-select" name="termino_id" data-ajax-select data-url="/api/select/terminos" data-parent-field="caso_id" data-parent-param="caso_id" data-empty-label="Sin termino"><option value="">Sin termino</option><?php if (($tarea['termino_id'] ?? null) !== null): ?><option value="<?= (int) $tarea['termino_id'] ?>" selected><?= e($tarea['termino_titulo'] ?? ('Termino #' . $tarea['termino_id'])) ?></option><?php endif; ?></select></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" value="<?= e($tarea['titulo']) ?>" required></div>
                <div class="row g-2"><div class="col-md-6"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><?php foreach ($prioridades as $prioridad): ?><option value="<?= e($prioridad) ?>" <?= $tarea['prioridad'] === $prioridad ? 'selected' : '' ?>><?= e($prioridad) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Vence</label><input class="form-control" name="fecha_vencimiento" type="date" min="<?= e($today) ?>" value="<?= e($tarea['fecha_vencimiento'] ?? '') ?>"></div></div>
                <div class="mb-3 mt-2"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" rows="5"><?= e($tarea['descripcion'] ?? '') ?></textarea></div>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </form>
        </div></div>
    </div>
</div>
