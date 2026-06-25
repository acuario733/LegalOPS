<?php declare(strict_types=1); $tipos = ['demandante','demandado','contraparte','testigo','tercero','apoderado','entidad','otro']; ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nueva parte</h2></div><div class="card-body">
            <form action="/casos/<?= (int) $caso['id'] ?>/partes" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo_parte"><?php foreach ($tipos as $tipo): ?><option value="<?= e($tipo) ?>"><?= e($tipo) ?></option><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                <div class="row g-2"><div class="col-sm-5"><label class="form-label">Tipo doc.</label><input class="form-control" name="tipo_documento"></div><div class="col-sm-7"><label class="form-label">Documento</label><input class="form-control" name="numero_documento"></div></div>
                <div class="mb-2 mt-2"><label class="form-label">Correo</label><input class="form-control" name="email" type="email"></div>
                <div class="mb-2"><label class="form-label">Telefono</label><input class="form-control" name="telefono"></div>
                <div class="mb-2"><label class="form-label">Direccion</label><input class="form-control" name="direccion"></div>
                <input type="hidden" name="estado" value="activo">
                <div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2"></textarea></div>
                <button class="btn btn-primary" type="submit">Crear parte</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="card-title mb-0"><?= e($caso['titulo']) ?></h2><a href="/casos/<?= (int) $caso['id'] ?>" class="btn btn-sm btn-outline-secondary">Caso</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Parte</th><th>Documento</th><th>Contacto</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($partes as $parte): ?><tr><td><strong><?= e($parte['nombre']) ?></strong><div class="small text-secondary"><?= e($parte['tipo_parte']) ?></div></td><td><span data-party-target="numero_documento-<?= (int) $parte['id'] ?>"><?= e($parte['numero_documento_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary" data-party-reveal="/casos/<?= (int) $caso['id'] ?>/partes/<?= (int) $parte['id'] ?>/revelar" data-field="numero_documento" data-target="numero_documento-<?= (int) $parte['id'] ?>"><i class="bi bi-eye"></i></button><?php endif; ?></td><td><div><span data-party-target="email-<?= (int) $parte['id'] ?>"><?= e($parte['email_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary" data-party-reveal="/casos/<?= (int) $caso['id'] ?>/partes/<?= (int) $parte['id'] ?>/revelar" data-field="email" data-target="email-<?= (int) $parte['id'] ?>"><i class="bi bi-eye"></i></button><?php endif; ?></div><div class="small text-secondary"><?= e($parte['telefono_masked']) ?></div></td><td><?= e($parte['estado']) ?></td><td><details><summary class="small">Editar</summary><form class="mt-2" action="/casos/<?= (int) $caso['id'] ?>/partes/<?= (int) $parte['id'] ?>" method="post" data-ajax-form><input type="hidden" name="_method" value="PATCH"><select class="form-select form-select-sm mb-1" name="tipo_parte"><?php foreach ($tipos as $tipo): ?><option value="<?= e($tipo) ?>" <?= $parte['tipo_parte'] === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option><?php endforeach; ?></select><input class="form-control form-control-sm mb-1" name="nombre" value="<?= e($parte['nombre']) ?>"><input class="form-control form-control-sm mb-1" name="numero_documento" placeholder="Mantener documento"><input class="form-control form-control-sm mb-1" name="email" placeholder="Mantener correo"><input type="hidden" name="estado" value="<?= e($parte['estado']) ?>"><button class="btn btn-sm btn-primary">Guardar</button></form><button class="btn btn-sm btn-outline-danger mt-2" data-action="/casos/<?= (int) $caso['id'] ?>/partes/<?= (int) $parte['id'] ?>/eliminar" data-confirm="Eliminar parte?">Eliminar</button></details></td></tr><?php endforeach; ?>
        <?php if ($partes === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay partes registradas.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    </div>
</div>
