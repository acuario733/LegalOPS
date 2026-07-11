<?php
declare(strict_types=1);
$selectedCliente = (int) ($filters['cliente_id'] ?? 0);
$selectedCaso = (int) ($filters['caso_id'] ?? 0);
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card"><div class="card-body"><form class="row g-2 align-items-end" method="get" action="/finanzas/saldos"><div class="col-md-5"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Toda la firma"><option value="">Toda la firma</option><?php foreach ($clientes as $cliente): ?><?php if ($selectedCliente === (int) $cliente['id']): ?><option value="<?= (int) $cliente['id'] ?>" selected><?= e($cliente['nombre_razon_social']) ?></option><?php endif; ?><?php endforeach; ?></select></div><div class="col-md-5"><label class="form-label">Caso</label><select class="form-select" name="caso_id" data-ajax-select data-url="/api/select/casos" data-parent-field="cliente_id" data-parent-param="cliente_id" data-empty-label="Todos"><option value="">Todos</option><?php foreach ($casos as $caso): ?><?php if ($selectedCaso === (int) $caso['id']): ?><option value="<?= (int) $caso['id'] ?>" selected><?= e($caso['titulo']) ?></option><?php endif; ?><?php endforeach; ?></select></div><div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Calcular</button></div></form></div></div>
    </div>
    <div class="col-md-3"><div class="small text-secondary">Honorarios</div><div class="fs-4 fw-semibold"><?= number_format((float) $resumen['total_honorarios'], 2) ?></div></div>
    <div class="col-md-3"><div class="small text-secondary">Pagos</div><div class="fs-4 fw-semibold"><?= number_format((float) $resumen['total_pagos'], 2) ?></div></div>
    <div class="col-md-3"><div class="small text-secondary">Gastos</div><div class="fs-4 fw-semibold"><?= number_format((float) $resumen['total_gastos'], 2) ?></div></div>
    <div class="col-md-3"><div class="small text-secondary">Saldo</div><div class="fs-4 fw-semibold" data-balance-value><?= number_format((float) $resumen['saldo'], 2) ?></div></div>
    <div class="col-12"><div class="small text-secondary">Formula: <?= e((string) ($formula['expresion'] ?? $resumen['formula']['expresion'] ?? 'saldo = honorarios_vigentes + gastos_cobrables - pagos_validos')) ?></div></div>
    <div class="col-12">
        <div class="card"><div class="card-header"><h2 class="card-title">Saldos derivados por cliente</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Cliente</th><th>Honorarios</th><th>Pagos</th><th>Gastos</th><th>Saldo</th></tr></thead><tbody>
        <?php foreach ($saldos['items'] as $saldo): ?><tr><td><strong><?= e($saldo['cliente_nombre']) ?></strong></td><td><?= number_format((float) $saldo['total_honorarios'], 2) ?></td><td><?= number_format((float) $saldo['total_pagos'], 2) ?></td><td><?= number_format((float) $saldo['total_gastos'], 2) ?></td><td><span data-balance-value><?= number_format((float) $saldo['saldo'], 2) ?></span></td></tr><?php endforeach; ?>
        <?php if ($saldos['items'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay saldos para calcular.</td></tr><?php endif; ?>
        </tbody></table></div><div class="card-footer small text-secondary">Total clientes: <?= (int) $saldos['total'] ?></div></div>
    </div>
</div>
