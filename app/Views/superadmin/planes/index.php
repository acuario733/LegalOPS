<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4"><div class="col-lg-4"><div class="card"><div class="card-header"><h2 class="card-title">Nuevo plan</h2></div><div class="card-body">
<form action="/superadmin/planes" method="post" data-ajax-form>
    <div class="mb-2"><label class="form-label">Código</label><input class="form-control" name="codigo" required></div>
    <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
    <div class="mb-2"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion"></textarea></div>
    <input type="hidden" name="estado" value="activo">
    <?php foreach (['usuarios','roles','catalogos'] as $resource): ?><div class="mb-2"><label class="form-label">Límite <?= e($resource) ?></label><input class="form-control" name="limites[<?= e($resource) ?>][limite]" type="number" min="0"><input type="hidden" name="limites[<?= e($resource) ?>][politica]" value="block"></div><?php endforeach; ?>
    <div class="mb-2"><label class="form-label">Limite clientes</label><input class="form-control" name="limites[clientes][limite]" type="number" min="0"><input type="hidden" name="limites[clientes][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite prospectos</label><input class="form-control" name="limites[prospectos][limite]" type="number" min="0"><input type="hidden" name="limites[prospectos][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite casos</label><input class="form-control" name="limites[casos][limite]" type="number" min="0"><input type="hidden" name="limites[casos][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite terminos</label><input class="form-control" name="limites[terminos][limite]" type="number" min="0"><input type="hidden" name="limites[terminos][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite audiencias</label><input class="form-control" name="limites[audiencias][limite]" type="number" min="0"><input type="hidden" name="limites[audiencias][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite tareas</label><input class="form-control" name="limites[tareas][limite]" type="number" min="0"><input type="hidden" name="limites[tareas][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite documentos</label><input class="form-control" name="limites[documentos][limite]" type="number" min="0"><input type="hidden" name="limites[documentos][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite honorarios</label><input class="form-control" name="limites[honorarios][limite]" type="number" min="0"><input type="hidden" name="limites[honorarios][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite pagos</label><input class="form-control" name="limites[pagos][limite]" type="number" min="0"><input type="hidden" name="limites[pagos][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite gastos</label><input class="form-control" name="limites[gastos][limite]" type="number" min="0"><input type="hidden" name="limites[gastos][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite exportaciones</label><input class="form-control" name="limites[exportaciones][limite]" type="number" min="0"><input type="hidden" name="limites[exportaciones][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite importaciones</label><input class="form-control" name="limites[importaciones][limite]" type="number" min="0"><input type="hidden" name="limites[importaciones][politica]" value="block"></div>
    <div class="mb-2"><label class="form-label">Limite tickets soporte</label><input class="form-control" name="limites[tickets_soporte][limite]" type="number" min="0"><input type="hidden" name="limites[tickets_soporte][politica]" value="block"></div>
    <button class="btn btn-primary">Crear plan</button>
</form></div></div></div>
<div class="col-lg-8"><div class="card"><div class="card-header"><h2 class="card-title">Planes</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Código</th><th>Nombre</th><th>Estado</th><th>Editar</th></tr></thead><tbody>
<?php foreach ($planes as $plan): ?><tr><td><code><?= e($plan['codigo']) ?></code></td><td><?= e($plan['nombre']) ?></td><td><?= e($plan['estado']) ?></td><td><details><summary class="small">Modificar</summary><form action="/superadmin/planes/<?= (int) $plan['id'] ?>" method="post" data-ajax-form class="mt-2"><input type="hidden" name="_method" value="PATCH"><input class="form-control form-control-sm mb-1" name="codigo" value="<?= e($plan['codigo']) ?>"><input class="form-control form-control-sm mb-1" name="nombre" value="<?= e($plan['nombre']) ?>"><input class="form-control form-control-sm mb-1" name="descripcion" value="<?= e($plan['descripcion']) ?>"><select class="form-select form-select-sm mb-1" name="estado"><option>activo</option><option>inactivo</option></select><button class="btn btn-sm btn-primary">Guardar</button></form></details></td></tr><?php endforeach; ?>
<?php if ($planes === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">No hay planes.</td></tr><?php endif; ?>
</tbody></table></div></div></div></div>
