<?php declare(strict_types=1); $cliente = $portal['cliente']; ?>
<div data-feedback hidden></div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1"><?= e($cliente['nombre_razon_social']) ?></h1><div class="text-secondary">Portal cliente LegalOPS</div></div>
    <button class="btn btn-outline-secondary" type="button" data-action="/logout" data-redirect="/login"><i class="bi bi-box-arrow-right"></i> Salir</button>
</div>
<?php if ($portal['pendientes_legales'] !== []): ?>
    <div class="card mb-4">
        <div class="card-header"><h2 class="card-title">Aceptaciones pendientes</h2></div>
        <div class="card-body">
            <?php foreach ($portal['pendientes_legales'] as $documento): ?>
                <form class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom py-2" action="/portal/legal/<?= (int) $documento['id'] ?>/aceptar" method="post" data-ajax-form>
                    <div><strong><?= e($documento['titulo']) ?></strong><div class="small text-secondary"><?= e($documento['tipo']) ?> v<?= e($documento['version']) ?></div></div>
                    <button class="btn btn-primary" type="submit">Aceptar</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<div class="row g-4" data-module="portal-cliente">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Casos autorizados</h2></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Caso</th><th>Estado</th><th>Nota</th></tr></thead><tbody>
                <?php foreach ($portal['casos'] as $caso): ?><tr><td><strong><?= e($caso['titulo']) ?></strong><div class="small text-secondary"><?= e($caso['radicado'] ?? '') ?></div></td><td><?= e($caso['estado']) ?></td><td><?= e($caso['observacion_publica'] ?? '') ?></td></tr><?php endforeach; ?>
                <?php if ($portal['casos'] === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">No hay casos publicados.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Documentos</h2></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Documento</th><th>Archivo</th><th></th></tr></thead><tbody>
                <?php foreach ($portal['documentos'] as $documento): ?><tr><td><strong><?= e($documento['titulo']) ?></strong><div class="small text-secondary"><?= e($documento['tipo_documental'] ?? '') ?></div></td><td><?= e($documento['nombre_original'] ?? 'Sin archivo') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/portal/documentos/<?= (int) $documento['id'] ?>/descargar"><i class="bi bi-download"></i></a></td></tr><?php endforeach; ?>
                <?php if ($portal['documentos'] === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">No hay documentos publicados.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Informacion financiera visible</h2></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Nota</th></tr></thead><tbody>
                <?php foreach ($portal['finanzas'] as $finanza): ?>
                    <?php $concepto = $finanza['honorario_concepto'] ?? $finanza['gasto_concepto'] ?? $finanza['metodo_pago'] ?? $finanza['tipo_finanza']; $monto = $finanza['honorario_monto'] ?? $finanza['gasto_monto'] ?? $finanza['pago_monto'] ?? 0; ?>
                    <tr><td><?= e($finanza['tipo_finanza']) ?></td><td><?= e($concepto) ?></td><td><?= number_format((float) $monto, 2) ?></td><td><?= e($finanza['observacion_publica'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($portal['finanzas'] === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">No hay informacion financiera publicada.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
