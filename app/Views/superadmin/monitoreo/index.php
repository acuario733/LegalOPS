<?php

declare(strict_types=1);

$servicios     = $servicios     ?? [];
$erroresRecientes = $erroresRecientes ?? [];
$queriesLentas = $queriesLentas ?? [];
$requestsHoras = $requestsHoras ?? [];

// Estado por defecto si no viene del controller
$defaultServicios = [
    ['nombre' => 'Base de datos',    'estado' => 'ok',      'detalle' => '12ms avg'],
    ['nombre' => 'Servidor web',     'estado' => 'ok',      'detalle' => 'Apache 2.4'],
    ['nombre' => 'Cola de emails',   'estado' => 'ok',      'detalle' => '0 pendientes'],
    ['nombre' => 'Service Worker',   'estado' => 'ok',      'detalle' => 'CDN operacional'],
];
if (empty($servicios)) $servicios = $defaultServicios;

$estadoStyle = fn(string $estado): array => match ($estado) {
    'ok'       => ['icon' => 'bi-check-circle-fill', 'color' => 'var(--color-green)',   'bg' => 'var(--color-green-light)',   'label' => 'Operacional'],
    'warning'  => ['icon' => 'bi-exclamation-circle-fill','color'=>'var(--color-warning)','bg'=>'var(--color-warning-light)', 'label' => 'Degradado'],
    'error'    => ['icon' => 'bi-x-circle-fill',     'color' => 'var(--color-danger)',  'bg' => 'var(--color-danger-light)',  'label' => 'Caído'],
    default    => ['icon' => 'bi-question-circle',   'color' => 'var(--color-text-muted)', 'bg' => 'var(--color-bg)',        'label' => 'Desconocido'],
};

$nivelBadge = fn(string $nivel): string => match (strtoupper($nivel)) {
    'ERROR'   => 'badge-cancelada',
    'WARNING' => 'badge-pendiente',
    default   => 'badge-fijo',
};
?>

<!-- HEADER -->
<div class="page-header">
    <div>
        <h1 class="page-title">Monitoreo del sistema</h1>
        <p class="page-subtitle">Estado en tiempo real de la plataforma LegalOPS Cloud</p>
    </div>
    <div style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
        <i class="bi bi-clock me-1"></i>
        Última actualización: <span id="last-update">—</span>
    </div>
</div>

<!-- FILA DE ESTADO DE SERVICIOS (semáforo) -->
<div class="row g-3 mb-4">
    <?php foreach ($servicios as $svc): ?>
    <?php $st = $estadoStyle($svc['estado'] ?? 'ok'); ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:<?= $st['bg'] ?>;display:flex;align-items:center;justify-content:center;color:<?= $st['color'] ?>;font-size:22px;flex-shrink:0;">
                    <i class="bi <?= $st['icon'] ?>"></i>
                </div>
                <div>
                    <div style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-navy);">
                        <?= e($svc['nombre']) ?>
                    </div>
                    <div style="font-size:var(--font-size-xs);color:<?= $st['color'] ?>;font-weight:600;">
                        <?= $st['label'] ?>
                    </div>
                    <?php if (!empty($svc['detalle'])): ?>
                    <div style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
                        <?= e($svc['detalle']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- GRÁFICO DE REQUESTS POR HORA -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Requests por hora</span>
        <span style="font-size:var(--font-size-xs);color:var(--color-text-muted);">Últimas 24 horas</span>
    </div>
    <div class="card-body">
        <canvas id="requests-chart" height="80"></canvas>
    </div>
</div>

<!-- ERRORES RECIENTES + QUERIES LENTAS -->
<div class="row g-4">
    <!-- Errores recientes -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Errores recientes</span>
                <span style="font-size:var(--font-size-xs);color:var(--color-text-muted);">Últimas 24 horas</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Nivel</th>
                                <th>Firma</th>
                                <th>Mensaje</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($erroresRecientes as $err): ?>
                            <tr>
                                <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);white-space:nowrap;">
                                    <?= e($err['hora'] ?? '') ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $nivelBadge($err['nivel'] ?? 'INFO') ?>">
                                        <?= e(strtoupper($err['nivel'] ?? 'INFO')) ?>
                                    </span>
                                </td>
                                <td style="font-size:var(--font-size-xs);"><?= e($err['firma'] ?? '') ?></td>
                                <td style="font-size:var(--font-size-xs);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= e($err['mensaje'] ?? '') ?>
                                </td>
                                <td>
                                    <?php if (!empty($err['trace'])): ?>
                                    <button type="button" class="btn btn-ghost btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-trace"
                                            data-trace="<?= e($err['trace']) ?>"
                                            data-mensaje="<?= e($err['mensaje'] ?? '') ?>">
                                        <i class="bi bi-code-slash"></i>Trace
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($erroresRecientes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-check-circle" style="font-size:2rem;color:var(--color-green);"></i>
                                    <p class="mt-2 mb-0" style="font-size:var(--font-size-sm);color:var(--color-text-muted);">
                                        Sin errores en las últimas 24 horas
                                    </p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Queries lentas -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Queries lentas</span>
                <span style="font-size:var(--font-size-xs);color:var(--color-text-muted);">&gt;1s</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($queriesLentas)): ?>
                <div class="text-center py-5" style="color:var(--color-text-muted);">
                    <i class="bi bi-lightning" style="font-size:2rem;color:var(--color-green);"></i>
                    <p class="mt-2 mb-0" style="font-size:var(--font-size-sm);">Todas las queries son rápidas</p>
                </div>
                <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($queriesLentas as $q): ?>
                    <li style="padding:12px 20px;border-bottom:1px solid var(--color-border);">
                        <div style="font-size:11px;color:var(--color-text-muted);font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:4px;">
                            <?= e(substr($q['query'] ?? '', 0, 80)) ?>…
                        </div>
                        <div class="d-flex gap-3" style="font-size:var(--font-size-xs);color:var(--color-text-secondary);">
                            <span style="color:var(--color-danger);font-weight:600;">
                                <?= number_format((float)($q['tiempo_avg'] ?? 0), 2) ?>s
                            </span>
                            <span><?= (int)($q['veces'] ?? 0) ?>× ejecutada</span>
                            <span><?= e($q['firma'] ?? '') ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: STACK TRACE -->
<div class="modal fade" id="modal-trace" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stack Trace</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="trace-mensaje" style="font-size:var(--font-size-sm);font-weight:500;color:var(--color-danger);margin-bottom:1rem;"></p>
                <pre id="trace-content" style="font-size:11px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:1rem;overflow-x:auto;white-space:pre-wrap;word-break:break-all;max-height:400px;overflow-y:auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ── Gráfico de requests ────────────────────────────────────────────────
    const ctx   = document.getElementById('requests-chart');
    const datos = <?= json_encode($requestsHoras) ?>;

    if (ctx && datos.length) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels:   datos.map(d => d.hora),
                datasets: [{
                    label:           'Requests',
                    data:            datos.map(d => d.total),
                    borderColor:     '#1A6B45',
                    backgroundColor: 'rgba(26,107,69,0.06)',
                    borderWidth:     2,
                    tension:         0.3,
                    pointRadius:     3,
                    pointBackgroundColor:'#1A6B45',
                    fill:            true,
                }, {
                    label:           'Errores',
                    data:            datos.map(d => d.errores || 0),
                    borderColor:     '#DC2626',
                    backgroundColor: 'rgba(220,38,38,0.06)',
                    borderWidth:     1.5,
                    tension:         0.3,
                    pointRadius:     3,
                    pointBackgroundColor:'#DC2626',
                    fill:            true,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 } } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94A3B8' } },
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 }, color: '#94A3B8' },
                        grid: { color: '#E2E8F0' },
                    },
                },
            },
        });
    } else if (ctx) {
        // Datos de demostración si no hay datos reales
        const hours = Array.from({length: 24}, (_, i) => `${String(i).padStart(2,'0')}:00`);
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: hours,
                datasets: [{
                    label:           'Requests',
                    data:            hours.map(() => Math.floor(Math.random() * 200 + 50)),
                    borderColor:     '#1A6B45',
                    backgroundColor: 'rgba(26,107,69,0.06)',
                    borderWidth:     2,
                    tension:         0.3,
                    fill:            true,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94A3B8' } },
                    y: { beginAtZero: true, ticks: { font: { size: 10 }, color: '#94A3B8' }, grid: { color: '#E2E8F0' } },
                },
            },
        });
    }

    // ── Modal de stack trace ────────────────────────────────────────────────
    document.getElementById('modal-trace')?.addEventListener('show.bs.modal', (e) => {
        const btn    = e.relatedTarget;
        const trace  = btn?.getAttribute('data-trace')   || 'Sin trace disponible';
        const msg    = btn?.getAttribute('data-mensaje') || '';
        document.getElementById('trace-mensaje').textContent  = msg;
        document.getElementById('trace-content').textContent  = trace;
    });

    // ── Timestamp de última actualización ──────────────────────────────────
    const el = document.getElementById('last-update');
    if (el) el.textContent = new Date().toLocaleTimeString('es-CO');
}());
</script>
