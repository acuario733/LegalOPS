<?php declare(strict_types=1); ?>
<?php $ficha360 = is_array($cliente['ficha360'] ?? null) ? $cliente['ficha360'] : ['resumen' => [], 'casos' => [], 'documentos' => [], 'finanzas' => [], 'portal' => [], 'actividad' => [], 'permisos' => []]; ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="card-title mb-0"><?= e($cliente['nombre_razon_social']) ?></h2>
                <span class="badge <?= $cliente['estado'] === 'activo' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e($cliente['estado']) ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= e($cliente['tipo_persona']) ?></dd>
                    <dt class="col-sm-4">Documento</dt><dd class="col-sm-8"><span data-sensitive-target="numero_documento"><?= e($cliente['numero_documento_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="numero_documento" data-target="numero_documento"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Correo</dt><dd class="col-sm-8"><span data-sensitive-target="email"><?= e($cliente['email_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="email" data-target="email"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Telefono</dt><dd class="col-sm-8"><span data-sensitive-target="telefono"><?= e($cliente['telefono_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="telefono" data-target="telefono"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Direccion</dt><dd class="col-sm-8"><span data-sensitive-target="direccion"><?= e($cliente['direccion_masked']) ?></span><?php if ($canReveal): ?> <button class="btn btn-sm btn-outline-secondary ms-1" data-client-reveal="/clientes/<?= (int) $cliente['id'] ?>/revelar" data-field="direccion" data-target="direccion"><i class="bi bi-eye"></i></button><?php endif; ?></dd>
                    <dt class="col-sm-4">Origen</dt><dd class="col-sm-8"><?= e(catalog_label($catalogos['origen_fuente'], $cliente['origen'] ?? '')) ?></dd>
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
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Ficha 360</h2></div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach (['casos' => ['Casos', 'casos'], 'documentos' => ['Documentos', 'documentos'], 'honorarios' => ['Honorarios', 'finanzas'], 'pagos' => ['Pagos', 'finanzas'], 'gastos' => ['Gastos', 'finanzas'], 'portal_accesos' => ['Portal', 'portal']] as $key => [$label, $scope]): ?>
                        <?php if (!($ficha360['permisos'][$scope] ?? false)) { continue; } ?>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-secondary"><?= e($label) ?></div>
                                <div class="fs-5 fw-semibold"><?= (int) ($ficha360['resumen'][$key] ?? 0) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($ficha360['permisos']['finanzas'] ?? false): ?>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4"><div class="small text-secondary">Honorarios</div><strong>COP <?= e(number_format((float) ($ficha360['finanzas']['honorarios'] ?? 0), 2, ',', '.')) ?></strong></div>
                        <div class="col-md-4"><div class="small text-secondary">Pagos</div><strong>COP <?= e(number_format((float) ($ficha360['finanzas']['pagos'] ?? 0), 2, ',', '.')) ?></strong></div>
                        <div class="col-md-4"><div class="small text-secondary">Gastos</div><strong>COP <?= e(number_format((float) ($ficha360['finanzas']['gastos'] ?? 0), 2, ',', '.')) ?></strong></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <?php if ($ficha360['permisos']['casos'] ?? false): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h2 class="card-title">Casos recientes</h2></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ficha360['casos'] as $caso): ?><a class="list-group-item list-group-item-action" href="/casos/<?= (int) $caso['id'] ?>"><strong><?= e($caso['titulo']) ?></strong><div class="small text-secondary"><?= e($caso['estado']) ?><?= ($caso['radicado'] ?? '') ? ' &middot; ' . e($caso['radicado']) : '' ?></div></a><?php endforeach; ?>
                        <?php if ($ficha360['casos'] === []): ?><div class="list-group-item text-secondary">Sin casos relacionados.</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($ficha360['permisos']['documentos'] ?? false): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h2 class="card-title">Documentos recientes</h2></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ficha360['documentos'] as $documento): ?><a class="list-group-item list-group-item-action" href="/documentos/<?= (int) $documento['id'] ?>"><strong><?= e($documento['titulo']) ?></strong><div class="small text-secondary"><?= e($documento['tipo_documental'] ?? 'Sin tipo') ?> &middot; <?= e($documento['estado']) ?></div></a><?php endforeach; ?>
                        <?php if ($ficha360['documentos'] === []): ?><div class="list-group-item text-secondary">Sin documentos relacionados.</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($ficha360['permisos']['portal'] ?? false): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h2 class="card-title">Actividad de portal</h2></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ficha360['portal'] as $acceso): ?><div class="list-group-item"><strong><?= e($acceso['accion']) ?></strong><div class="small text-secondary"><?= e($acceso['usuario'] ?? 'Usuario portal') ?> &middot; <?= e($acceso['created_at']) ?></div></div><?php endforeach; ?>
                        <?php if ($ficha360['portal'] === []): ?><div class="list-group-item text-secondary">Sin actividad de portal.</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($ficha360['permisos']['actividad'] ?? false): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h2 class="card-title">Auditoria reciente</h2></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ficha360['actividad'] as $evento): ?><div class="list-group-item"><strong><?= e($evento['accion']) ?></strong><div class="small text-secondary"><?= e($evento['modulo']) ?> &middot; <?= e($evento['severidad']) ?> &middot; <?= e($evento['created_at']) ?></div></div><?php endforeach; ?>
                        <?php if ($ficha360['actividad'] === []): ?><div class="list-group-item text-secondary">Sin auditoria relacionada.</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
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
                        <div class="col-md-4"><label class="form-label">Tipo doc.</label><select class="form-select" name="tipo_documento"><option value="">Seleccione</option><?php foreach ($catalogos['tipo_documento'] as $item): ?><option value="<?= e($item['codigo']) ?>" <?= ($cliente['tipo_documento'] ?? '') === $item['codigo'] ? 'selected' : '' ?>><?= e($item['codigo']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-8"><label class="form-label">Documento</label><input class="form-control" name="numero_documento" placeholder="Mantener actual"></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" name="email" type="email" placeholder="Mantener actual"></div>
                        <div class="col-md-6"><label class="form-label">Telefono</label><input class="form-control" name="telefono" placeholder="Mantener actual"></div>
                    </div>
                    <div class="mb-2 mt-2"><label class="form-label">Direccion</label><input class="form-control" name="direccion" placeholder="Mantener actual"></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Origen</label><select class="form-select" name="origen"><option value="">Seleccione</option><?php foreach ($catalogos['origen_fuente'] as $item): ?><option value="<?= e($item['codigo']) ?>" <?= ($cliente['origen'] ?? '') === $item['codigo'] ? 'selected' : '' ?>><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="activo" <?= $cliente['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= $cliente['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
                    </div>
                    <input type="hidden" name="tratamiento_datos_autorizado" value="0">
                    <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="tratamiento_datos_autorizado" value="1" <?= (int) $cliente['tratamiento_datos_autorizado'] === 1 ? 'checked' : '' ?>><span class="form-check-label">Autoriza tratamiento de datos</span></label>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Medio</label><select class="form-select" name="autorizacion_medio"><option value="">Seleccione</option><?php foreach ($catalogos['medio'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Version</label><input class="form-control" name="autorizacion_version"></div>
                    </div>
                    <div class="mb-3 mt-2"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="4"><?= e($cliente['observaciones'] ?? '') ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>
