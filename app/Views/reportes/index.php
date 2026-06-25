<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="card" data-module="reportes">
    <div class="card-header"><h2 class="card-title">Reportes CSV disponibles</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Reporte</th><th>Formato</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reportes as $tipo => $nombre): ?>
                <tr>
                    <td><strong><?= e($nombre) ?></strong><div class="small text-secondary"><?= e($tipo) ?></div></td>
                    <td><span class="badge text-bg-secondary">CSV</span></td>
                    <td class="text-end"><a class="btn btn-primary btn-sm" href="/reportes/<?= e($tipo) ?>/exportar"><i class="bi bi-download"></i> Exportar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($reportes === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">No hay reportes disponibles para sus permisos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
