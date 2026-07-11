<?php
declare(strict_types=1);
$estados = ['activo','cerrado','archivado'];
$prioridades = ['baja','media','alta','critica'];
$selectedCliente = (int) ($filters['cliente_id'] ?? 0);
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nuevo caso</h2></div><div class="card-body">
            <form action="/casos" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Seleccione" required><option value="">Seleccione</option></select></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required></div>
                <div class="row g-2"><div class="col-sm-6"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><?php foreach ($prioridades as $prioridad): ?><option value="<?= e($prioridad) ?>" <?= $prioridad === 'media' ? 'selected' : '' ?>><?= e($prioridad) ?></option><?php endforeach; ?></select></div><div class="col-sm-6"><label class="form-label">Radicado</label><input class="form-control" name="radicado"></div></div>
                <div class="row g-2 mt-1"><div class="col-sm-6"><label class="form-label">Tipo</label><select class="form-select" name="tipo_proceso"><option value="">Seleccione</option><?php foreach ($catalogos['tipo_caso'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div><div class="col-sm-6"><label class="form-label">Jurisdiccion</label><select class="form-select" name="jurisdiccion"><option value="">Seleccione</option><?php foreach ($catalogos['jurisdiccion'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Despacho</label><select class="form-select" name="despacho"><option value="">Seleccione</option><?php foreach ($catalogos['despacho'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id" data-ajax-select data-url="/api/select/usuarios" data-empty-label="Sin asignar"><option value="">Sin asignar</option></select></div>
                <div class="mb-2"><label class="form-label">Fecha apertura</label><input class="form-control" name="fecha_apertura" type="date" value="<?= e(date('Y-m-d')) ?>"></div>
                <input type="hidden" name="estado" value="activo">
                <div class="mb-3"><label class="form-label">Descripcion</label><textarea class="form-control" name="descripcion" rows="2"></textarea></div>
                <button class="btn btn-primary" type="submit">Crear caso</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get" action="/casos"><div class="col-md-5"><label class="form-label">Busqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div><div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Todos"><option value="">Todos</option><?php foreach ($clientes as $cliente): ?><?php if ($selectedCliente === (int) $cliente['id']): ?><option value="<?= (int) $cliente['id'] ?>" selected><?= e($cliente['nombre_razon_social']) ?></option><?php endif; ?><?php endforeach; ?></select></div><div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div></form></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Expedientes</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Caso</th><th>Cliente</th><th>Estado</th><th>Responsable</th><th></th></tr></thead><tbody>
        <?php foreach ($casos['items'] as $caso): ?><tr><td><strong><?= e($caso['titulo']) ?></strong><div class="small text-secondary"><?= e($caso['radicado'] ?? '') ?></div></td><td><?= e($caso['cliente_nombre']) ?></td><td><span class="badge text-bg-secondary"><?= e($caso['estado']) ?></span><div class="small text-secondary"><?= e($caso['prioridad']) ?></div></td><td><?= e($caso['responsable_nombre'] ?? 'Sin asignar') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/casos/<?= (int) $caso['id'] ?>">Ver</a></td></tr><?php endforeach; ?>
        <?php if ($casos['items'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay casos.</td></tr><?php endif; ?>
        </tbody></table></div><div class="card-footer small text-secondary">Total: <?= (int) $casos['total'] ?></div></div>
    </div>
</div>
