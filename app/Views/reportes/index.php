<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="card" data-module="reportes">
    <div class="card-header"><h2 class="card-title">Reportes CSV disponibles</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Reporte</th><th>Formato</th><th>Filtros</th></tr></thead>
            <tbody>
            <?php foreach ($reportes as $tipo => $nombre): ?>
                <tr>
                    <td><strong><?= e($nombre) ?></strong><div class="small text-secondary"><?= e($tipo) ?></div></td>
                    <td><span class="badge text-bg-secondary">CSV</span></td>
                    <td>
                        <details>
                            <summary class="small">Preparar exportacion</summary>
                            <form class="row g-2 align-items-end mt-2" method="get" action="/reportes/<?= e($tipo) ?>/exportar" data-report-form>
                                <div class="col-md-3"><label class="form-label">Desde</label><input class="form-control form-control-sm" name="desde" type="date"></div>
                                <div class="col-md-3"><label class="form-label">Hasta</label><input class="form-control form-control-sm" name="hasta" type="date"></div>
                                <div class="col-md-3"><label class="form-label">Cliente</label><select class="form-select form-select-sm" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Todos"><option value="">Todos</option></select></div>
                                <div class="col-md-3"><label class="form-label">Caso</label><select class="form-select form-select-sm" name="caso_id" data-ajax-select data-url="/api/select/casos" data-parent-field="cliente_id" data-parent-param="cliente_id" data-empty-label="Todos"><option value="">Todos</option></select></div>
                                <div class="col-md-3"><label class="form-label">Estado</label><input class="form-control form-control-sm" name="estado" maxlength="40"></div>
                                <div class="col-md-3"><label class="form-label">Tipo caso</label><select class="form-select form-select-sm" name="tipo_caso"><option value="">Todos</option><?php foreach ($catalogos['tipo_caso'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['etiqueta']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-2"><label class="form-label">Moneda</label><select class="form-select form-select-sm" name="moneda"><option value="">Todas</option><?php foreach ($catalogos['moneda'] as $item): ?><option value="<?= e($item['codigo']) ?>"><?= e($item['codigo']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-2"><label class="form-label">Finanza</label><select class="form-select form-select-sm" name="tipo_finanza"><option value="">Todas</option><option value="honorario">Honorario</option><option value="pago">Pago</option><option value="gasto">Gasto</option></select></div>
                                <div class="col-md-2"><label class="form-label">Min</label><input class="form-control form-control-sm" name="monto_min" type="number" min="0" step="0.01"></div>
                                <div class="col-md-2"><label class="form-label">Max</label><input class="form-control form-control-sm" name="monto_max" type="number" min="0" step="0.01"></div>
                                <div class="col-md-3"><label class="form-label">Modulo</label><input class="form-control form-control-sm" name="modulo" maxlength="120"></div>
                                <div class="col-md-3"><label class="form-label">Accion</label><input class="form-control form-control-sm" name="accion" maxlength="120"></div>
                                <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-download"></i> Exportar</button></div>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($reportes === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">No hay reportes disponibles para sus permisos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
