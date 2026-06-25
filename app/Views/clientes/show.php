<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="card-title mb-0"><?= e($cliente['nombre_razon_social']) ?></h2>
                <span class="badge text-bg-secondary"><?= e($cliente['estado']) ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= e($cliente['tipo_persona']) ?></dd>
                    <dt class="col-sm-4">Documento</dt><dd class="col-sm-8"><span data-sensitive-target="numero_documento"><?= e($cliente['numero_documento_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="numero_documento" data-target="numero_documento"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Correo</dt><dd class="col-sm-8"><span data-sensitive-target="email"><?= e($cliente['email_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="email" data-target="email"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Telefono</dt><dd class="col-sm-8"><span data-sensitive-target="telefono"><?= e($cliente['telefono_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="telefono" data-target="telefono"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Direccion</dt><dd class="col-sm-8"><span data-sensitive-target="direccion"><?= e($cliente['direccion_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="direccion" data-target="direccion"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Origen</dt><dd class="col-sm-8"><?= e($cliente['origen'] ?? '') ?></dd>
                    <dt class="col-sm-4">Datos</dt><dd class="col-sm-8"><?= (int) $cliente['tratamiento_datos_autorizado'] === 1 ? 'Autorizado' : 'Pendiente' ?></dd>
                </dl>
            </div>
            <div class="card-footer d-flex gap-2">
                <a class="btn btn-outline-secondary" href="/clientes">Volver</a>
                <button class="btn btn-outline-danger" data-action="/clientes/<?= (int) $cliente['id'] ?>/eliminar" data-confirm="Eliminar cliente?">Eliminar</button>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><h2 class="card-title">Autorizaciones</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Tipo</th><th>Medio</th><th>Fecha</th></tr></thead>
                    <tbody>
                    <?php foreach ($cliente['autorizaciones'] as $autorizacion): ?><tr><td><?= e($autorizacion['tipo']) ?></td><td><?= e($autorizacion['medio']) ?></td><td><?= e($autorizacion['created_at']) ?></td></tr><?php endforeach; ?>
                    <?php if ($cliente['autorizaciones'] === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">Sin autorizaciones.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Editar cliente</h2></div>
            <div class="card-body">
                <form action="/clientes/<?= (int) $cliente['id'] ?>" method="post" data-ajax-form>
                    <input type="hidden" name="_method" value="PATCH">
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" name="tipo_persona"><option value="natural" <?= $cliente['tipo_persona'] === 'natural' ? 'selected' : '' ?>>Natural</option><option value="juridica" <?= $cliente['tipo_persona'] === 'juridica' ? 'selected' : '' ?>>Juridica</option></select></div>
                        <div class="col-md-8"><label class="form-label">Nombre o razon social</label><input class="form-control" name="nombre_razon_social" value="<?= e($cliente['nombre_razon_social']) ?>" required></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4"><label class="form-label">Tipo doc.</label><input class="form-control" name="tipo_documento" value="<?= e($cliente['tipo_documento'] ?? '') ?>"></div>
                        <div class="col-md-8"><label class="form-label">Documento</label><input class="form-control" name="numero_documento" placeholder="Mantener actual"></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" name="email" type="email" placeholder="Mantener actual"></div>
                        <div class="col-md-6"><label class="form-label">Telefono</label><input class="form-control" name="telefono" placeholder="Mantener actual"></div>
                    </div>
                    <div class="mb-2 mt-2"><label class="form-label">Direccion</label><input class="form-control" name="direccion" placeholder="Mantener actual"></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Origen</label><input class="form-control" name="origen" value="<?= e($cliente['origen'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="activo" <?= $cliente['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= $cliente['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
                    </div>
                    <input type="hidden" name="tratamiento_datos_autorizado" value="0">
                    <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="tratamiento_datos_autorizado" value="1" <?= (int) $cliente['tratamiento_datos_autorizado'] === 1 ? 'checked' : '' ?>><span class="form-check-label">Autoriza tratamiento de datos</span></label>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Medio</label><input class="form-control" name="autorizacion_medio"></div>
                        <div class="col-md-6"><label class="form-label">Version</label><input class="form-control" name="autorizacion_version"></div>
                    </div>
                    <div class="mb-3 mt-2"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="4"><?= e($cliente['observaciones'] ?? '') ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>
