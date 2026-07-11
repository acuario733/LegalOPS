<?php
declare(strict_types=1);
$estados = ['vigente','proximo','critico','vencido','cumplido'];
$prioridades = ['baja','media','alta','critica'];
$selectedCaso = (int) ($filters['caso_id'] ?? 0);
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nuevo termino</h2></div><div class="card-body">
            <form action="/terminos" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-empty-label="Sin caso"><option value="">Sin caso</option><?php foreach ($casos as $caso): ?><?php if ($selectedCaso === (int) $caso['id']): ?><option value="<?= (int) $caso['id'] ?>" selected><?= e($caso['titulo']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="form-label">Tarea vinculada</label><select class="form-select" name="tarea_id" data-ajax-select data-url="/api/select/tareas" data-parent-field="caso_id" data-parent-param="caso_id" data-empty-label="Sin tarea"><option value="">Sin tarea</option></select></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required></div>
                <div class="row g-2"><div class="col-sm-6"><label class="form-label">Inicio</label><input class="form-control" name="fecha_inicio" type="date" value="<?= e(date('Y-m-d')) ?>"></div><div class="col-sm-6"><label class="form-label">Vence</label><input class="form-control" name="fecha_vencimiento" type="date" value="<?= e(date('Y-m-d')) ?>" required></div></div>
                <div class="row g-2 mt-1"><div class="col-sm-6"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><?php foreach ($prioridades as $prioridad): ?><option value="<?= e($prioridad) ?>" <?= $prioridad === 'media' ? 'selected' : '' ?>><?= e($prioridad) ?></option><?php endforeach; ?></select></div><div class="col-sm-6"><label class="form-label">Alerta dias</label><input class="form-control" name="alerta_dias" type="number" min="0" max="90" value="3"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id" data-ajax-select data-url="/api/select/usuarios" data-empty-label="Sin asignar"><option value="">Sin asignar</option></select></div>
                <input type="hidden" name="estado" value="vigente">
                <div class="mb-3"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" rows="3"></textarea></div>
                <button class="btn btn-primary" type="submit">Crear termino</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get" action="/terminos"><div class="col-md-4"><label class="form-label">Busqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div><div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-empty-label="Todos"><option value="">Todos</option><?php foreach ($casos as $caso): ?><?php if ($selectedCaso === (int) $caso['id']): ?><option value="<?= (int) $caso['id'] ?>" selected><?= e($caso['titulo']) ?></option><?php endif; ?><?php endforeach; ?></select></div><div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div></form></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Control de terminos</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Termino</th><th>Caso</th><th>Vence</th><th>Estado</th><th>Responsable</th><th></th></tr></thead><tbody>
        <?php foreach ($terminos['items'] as $termino): ?><tr data-term-state="<?= e($termino['estado_visual']) ?>"><td><strong><?= e($termino['titulo']) ?></strong><div class="small text-secondary"><?= e($termino['prioridad']) ?></div></td><td><?= e($termino['caso_titulo'] ?? 'Sin caso') ?></td><td><?= e($termino['fecha_vencimiento']) ?></td><td><span class="badge text-bg-secondary" data-term-badge><?= e($termino['estado_visual']) ?></span></td><td><?= e($termino['responsable_nombre'] ?? 'Sin asignar') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/terminos/<?= (int) $termino['id'] ?>">Ver</a></td></tr><?php endforeach; ?>
        <?php if ($terminos['items'] === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">No hay terminos.</td></tr><?php endif; ?>
        </tbody></table></div><div class="card-footer small text-secondary">Total: <?= (int) $terminos['total'] ?></div></div>
    </div>
</div>
