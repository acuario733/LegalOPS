<?php

declare(strict_types=1);

$jobs = is_array($jobs ?? null) ? $jobs : [];
$pending = is_array($jobs['pending'] ?? null) ? $jobs['pending'] : [];
$processing = is_array($jobs['processing'] ?? null) ? $jobs['processing'] : [];
$failed = is_array($jobs['failed'] ?? null) ? $jobs['failed'] : [];
$stats = is_array($jobs['stats'] ?? null) ? $jobs['stats'] : [];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 mb-1">Monitoreo de jobs</h1>
        <p class="text-muted mb-0">Estado operativo de las colas y reintentos.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="/superadmin/jobs" title="Actualizar">
        <i class="bi bi-arrow-clockwise"></i>
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><p class="stat-label">Pendientes</p><div class="stat-value"><?= count($pending) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><p class="stat-label">Procesando</p><div class="stat-value"><?= count($processing) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><p class="stat-label">Fallidos</p><div class="stat-value text-danger"><?= count($failed) ?></div></div></div>
</div>

<?php foreach ([['Pendientes', $pending], ['Procesando', $processing]] as [$label, $rows]): ?>
<section class="mb-4">
    <h2 class="h6 mb-2"><?= e($label) ?></h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>ID</th><th>Cola</th><th>Job</th><th>Intentos</th><th>Payload</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="5" class="text-muted text-center py-3">Sin jobs.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><?= e($row['queue'] ?? '') ?></td>
                    <td><code><?= e($row['job_class'] ?? '') ?></code></td>
                    <td><?= (int) ($row['attempts'] ?? 0) ?></td>
                    <td><pre class="mb-0 small" style="max-width:560px;white-space:pre-wrap"><?= e($row['payload_readable'] ?? '{}') ?></pre></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>

<section class="mb-4">
    <h2 class="h6 mb-2">Fallidos</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>ID</th><th>Job</th><th>Error</th><th>Payload</th><th></th></tr></thead>
            <tbody>
            <?php if ($failed === []): ?><tr><td colspan="5" class="text-muted text-center py-3">Sin jobs fallidos.</td></tr><?php endif; ?>
            <?php foreach ($failed as $row): ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><code><?= e($row['job_class'] ?? '') ?></code></td>
                    <td style="max-width:360px"><?= e(mb_substr((string) ($row['exception'] ?? ''), 0, 500)) ?></td>
                    <td><pre class="mb-0 small" style="max-width:460px;white-space:pre-wrap"><?= e($row['payload_readable'] ?? '{}') ?></pre></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm" type="button"
                                data-action="/superadmin/jobs/failed/<?= (int) $row['id'] ?>/retry"
                                data-redirect="/superadmin/jobs" title="Reintentar">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section>
    <h2 class="h6 mb-2">Estadisticas diarias</h2>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Fecha</th><th>Cola</th><th>Procesados</th><th>Fallidos</th><th>Promedio</th></tr></thead>
            <tbody>
            <?php if ($stats === []): ?><tr><td colspan="5" class="text-muted text-center py-3">Sin estadisticas.</td></tr><?php endif; ?>
            <?php foreach ($stats as $row): ?>
                <tr><td><?= e($row['fecha'] ?? '') ?></td><td><?= e($row['queue'] ?? '') ?></td><td><?= (int) $row['procesados'] ?></td><td><?= (int) $row['fallidos'] ?></td><td><?= (int) $row['tiempo_promedio_ms'] ?> ms</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
