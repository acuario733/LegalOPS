<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Nuevo cliente</h2></div>
            <div class="card-body">
                <p class="small text-secondary mb-3"><span class="text-danger">*</span> Campo requerido</p>
                <form action="/clientes" method="post" class="needs-validation" data-ajax-form novalidate>
                    <div class="mb-2">
                        <label class="form-label" for="tipo_persona">Tipo</label>
                        <select class="form-select" name="tipo_persona" id="tipo_persona">
                            <option value="natural">Natural</option>
                            <option value="juridica">Juridica</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="nombre_razon_social">Nombre o razón social <span class="text-danger">*</span></label>
                        <input class="form-control" name="nombre_razon_social" id="nombre_razon_social"
                               required minlength="3" maxlength="180"
                               data-field-validator="required|minLength:3"
                               aria-describedby="nombre_razon_social-error"
                               placeholder="Ej: Empresa S.A.">
                        <small id="nombre_razon_social-error" class="d-block text-danger mt-1" hidden></small>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-5">
                            <label class="form-label" for="tipo_documento">Tipo doc.</label>
                            <select class="form-select" name="tipo_documento" id="tipo_documento">
                                <option value="">Seleccione</option>
                                <?php foreach ($catalogos['tipo_documento'] as $item): ?>
                                    <option value="<?= e($item['codigo']) ?>"><?= e($item['codigo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label" for="numero_documento">Documento</label>
                            <input class="form-control" name="numero_documento" id="numero_documento"
                                   data-field-validator="documento"
                                   aria-describedby="numero_documento-error"
                                   placeholder="Ej: 123456789">
                            <small id="numero_documento-error" class="d-block text-danger mt-1" hidden></small>
                        </div>
                    </div>
                    <div class="mb-2 mt-2">
                        <label class="form-label" for="email">Correo <span class="text-danger">*</span></label>
                        <input class="form-control" name="email" id="email" type="email"
                               required
                               data-field-validator="required|email"
                               aria-describedby="email-error"
                               placeholder="contacto@empresa.com">
                        <small id="email-error" class="d-block text-danger mt-1" hidden></small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input class="form-control" name="telefono" id="telefono"
                               data-field-validator="phone"
                               aria-describedby="telefono-error"
                               placeholder="Ej: +573001234567">
                        <small id="telefono-error" class="d-block text-danger mt-1" hidden></small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="direccion">Dirección</label>
                        <input class="form-control" name="direccion" id="direccion">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="origen">Origen</label>
                        <select class="form-select" name="origen" id="origen">
                            <option value="">Seleccione</option>
                            <?php foreach ($catalogos['origen_fuente'] as $item): ?>
                                <option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="estado" value="activo">
                    <input type="hidden" name="tratamiento_datos_autorizado" value="0">
                    <label class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="tratamiento_datos_autorizado" value="1">
                        <span class="form-check-label">Autoriza tratamiento de datos</span>
                    </label>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="autorizacion_medio">Medio</label>
                            <select class="form-select" name="autorizacion_medio" id="autorizacion_medio">
                                <option value="">Seleccione</option>
                                <?php foreach ($catalogos['medio'] as $item): ?>
                                    <option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="autorizacion_version">Versión</label>
                            <input class="form-control" name="autorizacion_version" id="autorizacion_version">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label" for="observaciones">Observaciones</label>
                        <textarea class="form-control" name="observaciones" id="observaciones" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" data-submit-btn>
                        <span class="spinner-border spinner-border-sm me-2" hidden data-spinner aria-hidden="true"></span>
                        <span data-text>Crear cliente</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="get" action="/clientes">
                    <div class="col-md-5"><label class="form-label">Búsqueda</label><input class="form-control" name="q" value="<?= e($filters['q'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo_persona"><option value="">Todos</option><option value="natural" <?= ($filters['tipo_persona'] ?? '') === 'natural' ? 'selected' : '' ?>>Natural</option><option value="juridica" <?= ($filters['tipo_persona'] ?? '') === 'juridica' ? 'selected' : '' ?>>Juridica</option></select></div>
                    <div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Clientes registrados</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-responsive-stack" data-table>
                    <thead class="table-light" style="position:sticky;top:0;z-index:1;">
                        <tr><th>Cliente</th><th>Documento</th><th>Contacto</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if ($clientes['items'] === []): ?>
                        <tr>
                            <td colspan="5">
                                <div class="d-flex align-items-center gap-3 py-3 text-secondary">
                                    <i class="bi bi-people fs-2"></i>
                                    <div>
                                        <strong>No hay clientes aún</strong>
                                        <p class="mb-0 small">Crea tu primer cliente usando el formulario.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes['items'] as $cliente): ?>
                            <tr>
                                <td data-label="Cliente"><strong><?= e($cliente['nombre_razon_social']) ?></strong><div class="small text-secondary"><?= e($cliente['tipo_persona']) ?></div></td>
                                <td data-label="Documento"><span class="small text-secondary"><?= e($cliente['tipo_documento'] ?? '') ?></span><div><?= e($cliente['numero_documento_masked']) ?></div></td>
                                <td data-label="Contacto"><div><?= e($cliente['email_masked']) ?></div><div class="small text-secondary"><?= e($cliente['telefono_masked']) ?></div></td>
                                <td data-label="Estado"><span class="badge <?= $cliente['estado'] === 'activo' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e($cliente['estado']) ?></span></td>
                                <td data-label="" class="text-end"><a class="btn btn-sm btn-outline-primary" href="/clientes/<?= (int) $cliente['id'] ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
                $total     = (int) $clientes['total'];
                $perPage   = 20;
                $page      = max(1, (int) ($filters['page'] ?? 1));
                $totalPages = max(1, (int) ceil($total / $perPage));
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="card-footer" aria-label="Paginación">
                    <ul class="pagination mb-0 small">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">← Anterior</a>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">Página <strong><?= $page ?></strong> de <strong><?= $totalPages ?></strong> (<?= $total ?> total)</span>
                        </li>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">Siguiente →</a>
                        </li>
                    </ul>
                </nav>
            <?php else: ?>
                <div class="card-footer small text-secondary">Total: <?= $total ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
