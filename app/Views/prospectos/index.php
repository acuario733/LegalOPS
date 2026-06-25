<?php declare(strict_types=1); $estados = ['nuevo','contactado','consulta','cotizacion','negociacion','ganado','perdido']; ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nuevo prospecto</h2></div><div class="card-body">
            <form action="/prospectos" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo_persona"><option value="natural">Natural</option><option value="juridica">Juridica</option></select></div>
                <div class="row g-2"><div class="col-sm-5"><label class="form-label">Tipo doc.</label><input class="form-control" name="tipo_documento"></div><div class="col-sm-7"><label class="form-label">Documento</label><input class="form-control" name="numero_documento"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Correo</label><input class="form-control" name="email" type="email"></div>
                <div class="mb-2"><label class="form-label">Telefono</label><input class="form-control" name="telefono"></div>
                <div class="mb-2"><label class="form-label">Empresa</label><input class="form-control" name="empresa"></div>
                <div class="row g-2"><div class="col-sm-6"><label class="form-label">Fuente</label><input class="form-control" name="fuente"></div><div class="col-sm-6"><label class="form-label">Valor estimado</label><input class="form-control" name="valor_estimado" type="number" step="0.01" min="0"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Responsable</label><select class="form-select" name="responsable_usuario_id"><option value="">Sin asignar</option><?php foreach ($usuarios as $usuario): ?><option value="<?= (int) $usuario['id'] ?>"><?= e($usuario['nombre']) ?></option><?php endforeach; ?></select></div>
                <input type="hidden" name="estado" value="nuevo"><input type="hidden" name="tratamiento_datos_autorizado" value="0">
                <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="tratamiento_datos_autorizado" value="1"><span class="form-check-label">Autoriza tratamiento de datos</span></label>
                <div class="mb-3"><label class="form-label">Notas</label><textarea class="form-control" name="notas" rows="2"></textarea></div>
                <button class="btn btn-primary" type="submit">Crear prospecto</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get" action="/prospectos"><div class="col-md-6"><label class="form-label">Busqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div><div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div></form></div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Pipeline</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Prospecto</th><th>Estado</th><th>Responsable</th><th>Valor</th><th></th></tr></thead><tbody>
        <?php foreach ($prospectos['items'] as $prospecto): ?><tr><td><strong><?= e($prospecto['nombre']) ?></strong><div class="small text-secondary"><?= e($prospecto['empresa'] ?? '') ?></div></td><td><?= e($prospecto['estado']) ?></td><td><?= e($prospecto['responsable_nombre'] ?? 'Sin asignar') ?></td><td><?= $prospecto['valor_estimado'] === null ? '' : e($prospecto['valor_estimado']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/prospectos/<?= (int) $prospecto['id'] ?>">Ver</a></td></tr><?php endforeach; ?>
        <?php if ($prospectos['items'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay prospectos.</td></tr><?php endif; ?>
        </tbody></table></div><div class="card-footer small text-secondary">Total: <?= (int) $prospectos['total'] ?></div></div>
    </div>
</div>
