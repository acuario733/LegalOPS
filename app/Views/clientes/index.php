<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Nuevo cliente</h2></div>
            <div class="card-body">
                <form action="/clientes" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo_persona"><option value="natural">Natural</option><option value="juridica">Juridica</option></select></div>
                    <div class="mb-2"><label class="form-label">Nombre o razon social</label><input class="form-control" name="nombre_razon_social" required></div>
                    <div class="row g-2">
                        <div class="col-sm-5"><label class="form-label">Tipo doc.</label><input class="form-control" name="tipo_documento"></div>
                        <div class="col-sm-7"><label class="form-label">Documento</label><input class="form-control" name="numero_documento"></div>
                    </div>
                    <div class="mb-2 mt-2"><label class="form-label">Correo</label><input class="form-control" name="email" type="email"></div>
                    <div class="mb-2"><label class="form-label">Telefono</label><input class="form-control" name="telefono"></div>
                    <div class="mb-2"><label class="form-label">Direccion</label><input class="form-control" name="direccion"></div>
                    <div class="mb-2"><label class="form-label">Origen</label><input class="form-control" name="origen"></div>
                    <input type="hidden" name="estado" value="activo">
                    <input type="hidden" name="tratamiento_datos_autorizado" value="0">
                    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="tratamiento_datos_autorizado" value="1"><span class="form-check-label">Autoriza tratamiento de datos</span></label>
                    <div class="row g-2">
                        <div class="col-sm-6"><label class="form-label">Medio</label><input class="form-control" name="autorizacion_medio"></div>
                        <div class="col-sm-6"><label class="form-label">Version</label><input class="form-control" name="autorizacion_version"></div>
                    </div>
                    <div class="mb-3 mt-2"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2"></textarea></div>
                    <button class="btn btn-primary" type="submit">Crear cliente</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="/clientes">
                    <div class="col-md-5"><label class="form-label">Busqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo_persona"><option value="">Todos</option><option value="natural" <?= ($filters['tipo_persona'] ?? '') === 'natural' ? 'selected' : '' ?>>Natural</option><option value="juridica" <?= ($filters['tipo_persona'] ?? '') === 'juridica' ? 'selected' : '' ?>>Juridica</option></select></div>
                    <div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Clientes registrados</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Cliente</th><th>Documento</th><th>Contacto</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($clientes['items'] as $cliente): ?>
                        <tr>
                            <td><strong><?= e($cliente['nombre_razon_social']) ?></strong><div class="small text-secondary"><?= e($cliente['tipo_persona']) ?></div></td>
                            <td><span class="small text-secondary"><?= e($cliente['tipo_documento'] ?? '') ?></span><div><?= e($cliente['numero_documento_masked']) ?></div></td>
                            <td><div><?= e($cliente['email_masked']) ?></div><div class="small text-secondary"><?= e($cliente['telefono_masked']) ?></div></td>
                            <td><?= e($cliente['estado']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/clientes/<?= (int) $cliente['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($clientes['items'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay clientes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-secondary">Total: <?= (int) $clientes['total'] ?></div>
        </div>
    </div>
</div>
