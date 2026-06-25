<?php declare(strict_types=1); $estados = ['pendiente','en_proceso','completada','vencida','cancelada']; $prioridades = ['baja','media','alta','critica']; ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nueva tarea</h2></div><div class="card-body">
            <form action="/tareas" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Caso</label><select class="form-select" name="caso_id"><option value="">Sin caso</option><?php foreach ($casos as $caso): ?><option value="<?= (int) $caso['id'] ?>"><?= e($caso['titulo']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="form-label">Termino vinculado</label><select class="form-select" name="termino_id"><option value="">Sin termino</option><?php foreach ($terminos as $termino): ?><option value="<?= (int) $termino['id'] ?>"><?= e($termino['titulo']) ?> (<?= e($termino['fecha_vencimiento']) ?>)</option><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required></div>
                <div class="row g-2"><div class="col-sm-6"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><?php foreach ($prioridades as $prioridad): ?><option value="<?= e($prioridad) ?>" <?= $prioridad === 'media' ? 'selected' : '' ?>><?= e($prioridad) ?></option><?php endforeach; ?></select></div><div class="col-sm-6"><label class="form-label">Vence</label><input class="form-control" name="fecha_vencimiento" type="date"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id"><option value="">Sin asignar</option><?php foreach ($usuarios as $usuario): ?><option value="<?= (int) $usuario['id'] ?>"><?= e($usuario['nombre']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" rows="3"></textarea></div>
                <button class="btn btn-primary" type="submit">Crear tarea</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get" action="/tareas"><div class="col-md-4"><label class="form-label">Busqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div><div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Caso</label><select class="form-select" name="caso_id"><option value="">Todos</option><?php foreach ($casos as $caso): ?><option value="<?= (int) $caso['id'] ?>" <?= (int) ($filters['caso_id'] ?? 0) === (int) $caso['id'] ? 'selected' : '' ?>><?= e($caso['titulo']) ?></option><?php endforeach; ?></select></div><div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div></form></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Lista de tareas</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tarea</th><th>Caso</th><th>Termino</th><th>Vence</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($tareas['items'] as $tarea): ?><tr data-task-state="<?= e($tarea['estado_visual']) ?>"><td><strong><?= e($tarea['titulo']) ?></strong><div class="small text-secondary"><?= e($tarea['prioridad']) ?> - <?= e($tarea['responsable_nombre'] ?? 'Sin asignar') ?></div></td><td><?= e($tarea['caso_titulo'] ?? 'Sin caso') ?></td><td><?= e($tarea['termino_titulo'] ?? 'Sin termino') ?></td><td><?= e($tarea['fecha_vencimiento'] ?? '') ?></td><td><span class="badge text-bg-secondary" data-task-badge><?= e($tarea['estado_visual']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/tareas/<?= (int) $tarea['id'] ?>">Ver</a></td></tr><?php endforeach; ?>
        <?php if ($tareas['items'] === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">No hay tareas.</td></tr><?php endif; ?>
        </tbody></table></div><div class="card-footer small text-secondary">Total: <?= (int) $tareas['total'] ?></div></div>
    </div>
</div>
