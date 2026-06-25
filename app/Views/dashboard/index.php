<?php declare(strict_types=1); $labels = ['clientes_activos' => 'Clientes activos', 'casos_activos' => 'Casos activos', 'terminos_criticos' => 'Terminos criticos', 'audiencias_proximas' => 'Audiencias proximas', 'tareas_pendientes' => 'Tareas pendientes']; ?>
<div data-feedback hidden></div>
<div class="row g-3 mb-4" data-module="dashboard">
    <?php foreach ($labels as $key => $label): ?>
        <?php if (array_key_exists($key, $summary['cards'])): ?>
            <div class="col-sm-6 col-xl">
                <div class="small-box text-bg-primary">
                    <div class="inner"><h3><?= (int) $summary['cards'][$key] ?></h3><p><?= e($label) ?></p></div>
                    <i class="small-box-icon bi bi-speedometer2"></i>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Documentos recientes</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Documento</th><th>Cliente</th><th>Actualizado</th><th></th></tr></thead>
                    <tbody>
                    <?php if (is_array($summary['documentos_recientes'])): ?>
                    <?php foreach ($summary['documentos_recientes'] as $documento): ?>
                        <tr>
                            <td><strong><?= e($documento['titulo']) ?></strong></td>
                            <td><?= e($documento['cliente_nombre'] ?? 'Sin cliente') ?></td>
                            <td class="small text-secondary"><?= e($documento['updated_at']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/documentos/<?= (int) $documento['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($summary['documentos_recientes'] === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">No hay documentos recientes.</td></tr><?php endif; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">Sin permiso para consultar documentos.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Resumen financiero</h2></div>
            <div class="card-body">
                <?php if (is_array($summary['finanzas'])): ?>
                    <dl class="row mb-0">
                        <dt class="col-6">Honorarios</dt><dd class="col-6 text-end"><?= number_format((float) $summary['finanzas']['honorarios'], 2) ?></dd>
                        <dt class="col-6">Pagos</dt><dd class="col-6 text-end"><?= number_format((float) $summary['finanzas']['pagos'], 2) ?></dd>
                        <dt class="col-6">Gastos</dt><dd class="col-6 text-end"><?= number_format((float) $summary['finanzas']['gastos'], 2) ?></dd>
                        <dt class="col-6">Saldo</dt><dd class="col-6 text-end fw-semibold"><?= number_format((float) $summary['finanzas']['saldo'], 2) ?></dd>
                    </dl>
                <?php else: ?>
                    <p class="text-secondary mb-0">Sin permiso para consultar finanzas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
