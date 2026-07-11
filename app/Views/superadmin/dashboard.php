<?php

declare(strict_types=1);

// Datos esperados del controller (con fallbacks seguros)
$stats = $stats ?? [
    'firmas_activas'  => 0,
    'usuarios_totales'=> 0,
    'mrr'             => 0,
    'tickets_abiertos'=> 0,
];
$actividadReciente = $actividadReciente ?? [];
$firmasEnRiesgo    = $firmasEnRiesgo    ?? [];
$crecimientoMeses  = $crecimientoMeses  ?? [];
?>

<!-- FILA DE STAT CARDS -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Firmas activas</p>
                    <div class="stat-value" data-count="<?= (int) $stats['firmas_activas'] ?>">
                        <?= number_format((int) $stats['firmas_activas']) ?>
                    </div>
                    <span class="stat-trend up">
                        <i class="bi bi-arrow-up-short"></i>
                        +2 este mes
                    </span>
                </div>
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--color-green-light);display:flex;align-items:center;justify-content:center;color:var(--color-green);font-size:20px;">
                    <i class="bi bi-buildings"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Usuarios totales</p>
                    <div class="stat-value" data-count="<?= (int) $stats['usuarios_totales'] ?>">
                        <?= number_format((int) $stats['usuarios_totales']) ?>
                    </div>
                    <span class="stat-trend up">
                        <i class="bi bi-arrow-up-short"></i>
                        +12 esta semana
                    </span>
                </div>
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--color-info-light);display:flex;align-items:center;justify-content:center;color:var(--color-info);font-size:20px;">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">MRR</p>
                    <div class="stat-value" data-count="<?= (float) $stats['mrr'] ?>" data-prefix="$" data-decimals="0">
                        $<?= number_format((float) $stats['mrr'], 0) ?>
                    </div>
                    <span class="stat-trend up">
                        <i class="bi bi-arrow-up-short"></i>
                        +8.3% vs mes anterior
                    </span>
                </div>
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:#FEF3C7;display:flex;align-items:center;justify-content:center;color:#92400E;font-size:20px;">
                    <i class="bi bi-currency-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Tickets abiertos</p>
                    <div class="stat-value" style="color: <?= (int)$stats['tickets_abiertos'] > 5 ? 'var(--color-danger)' : 'var(--color-navy)' ?>">
                        <?= (int) $stats['tickets_abiertos'] ?>
                    </div>
                    <?php if ((int) $stats['tickets_abiertos'] > 5): ?>
                    <span class="stat-trend" style="color:var(--color-danger);">
                        <i class="bi bi-exclamation-triangle"></i>
                        Requiere atención
                    </span>
                    <?php else: ?>
                    <span class="stat-trend up"><i class="bi bi-check2"></i> Normal</span>
                    <?php endif; ?>
                </div>
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--color-danger-light);display:flex;align-items:center;justify-content:center;color:var(--color-danger);font-size:20px;">
                    <i class="bi bi-life-preserver"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GRÁFICO + FIRMAS EN RIESGO -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Nuevas firmas por mes</span>
                <span style="font-size:var(--font-size-xs);color:var(--color-text-muted);">Últimos 12 meses</span>
            </div>
            <div class="card-body">
                <canvas id="growth-chart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Firmas en riesgo</span>
                <span style="font-size:var(--font-size-xs);color:var(--color-text-muted);">Sin actividad &gt;14 días</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($firmasEnRiesgo)): ?>
                <div class="text-center py-5" style="color:var(--color-text-muted);">
                    <i class="bi bi-check-circle" style="font-size:2rem;color:var(--color-green);"></i>
                    <p class="mt-2 mb-0" style="font-size:var(--font-size-sm);">Sin firmas en riesgo</p>
                </div>
                <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($firmasEnRiesgo as $firma): ?>
                    <li style="padding:12px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:12px;">
                        <div style="width:34px;height:34px;border-radius:var(--radius-full);background:var(--color-warning-light);color:var(--color-warning);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(mb_substr((string)($firma['nombre'] ?? 'F'), 0, 1)) ?>
                        </div>
                        <div style="flex:1;overflow:hidden;">
                            <div style="font-size:var(--font-size-sm);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= e($firma['nombre'] ?? '') ?>
                            </div>
                            <div style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
                                Último: <?= e($firma['ultimo_login'] ?? 'Nunca') ?>
                                · <?= e($firma['plan'] ?? '') ?>
                            </div>
                        </div>
                        <a href="/superadmin/firmas/<?= e($firma['id'] ?? '') ?>"
                           class="btn btn-outline-primary btn-sm" style="flex-shrink:0;">Ver</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ACTIVIDAD RECIENTE DEL SISTEMA -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Actividad reciente del sistema</span>
        <a href="/superadmin/auditoria" style="font-size:var(--font-size-xs);color:var(--color-green);">Ver todo</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Tipo</th>
                        <th>Firma</th>
                        <th>Descripción</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($actividadReciente)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4" style="color:var(--color-text-muted);">
                            Sin actividad reciente
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($actividadReciente as $evento): ?>
                    <?php
                    $tipoIconos = [
                        'nueva_firma' => ['icon' => 'bi-buildings',       'color' => 'var(--color-green)',   'bg' => 'var(--color-green-light)'],
                        'plan_upgrade'=> ['icon' => 'bi-arrow-up-circle', 'color' => 'var(--color-info)',    'bg' => 'var(--color-info-light)'],
                        'error'       => ['icon' => 'bi-x-circle',        'color' => 'var(--color-danger)',  'bg' => 'var(--color-danger-light)'],
                        'login'       => ['icon' => 'bi-person-check',    'color' => 'var(--color-warning)', 'bg' => 'var(--color-warning-light)'],
                    ];
                    $tipo = $tipoIconos[$evento['tipo'] ?? ''] ?? $tipoIconos['login'];
                    ?>
                    <tr>
                        <td>
                            <div style="width:32px;height:32px;border-radius:var(--radius-sm);background:<?= $tipo['bg'] ?>;display:flex;align-items:center;justify-content:center;color:<?= $tipo['color'] ?>;">
                                <i class="bi <?= e($tipo['icon']) ?>"></i>
                            </div>
                        </td>
                        <td style="font-size:var(--font-size-xs);font-weight:600;text-transform:uppercase;color:var(--color-text-secondary);">
                            <?= e($evento['tipo'] ?? '') ?>
                        </td>
                        <td><?= e($evento['firma'] ?? '') ?></td>
                        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= e($evento['descripcion'] ?? '') ?>
                        </td>
                        <td style="color:var(--color-text-muted);white-space:nowrap;font-size:var(--font-size-xs);">
                            <?= e($evento['fecha'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ctx   = document.getElementById('growth-chart');
    if (!ctx) return;

    const labels = <?= json_encode(array_column($crecimientoMeses, 'mes'), JSON_UNESCAPED_UNICODE) ?>;
    const data   = <?= json_encode(array_column($crecimientoMeses, 'nuevas')) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label:           'Nuevas firmas',
                data,
                borderColor:     '#1A6B45',
                backgroundColor: 'rgba(26,107,69,0.08)',
                borderWidth:     2,
                tension:         0.4,
                pointBackgroundColor:'#1A6B45',
                pointRadius:     4,
                fill:            true,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.parsed.y} firma${ctx.parsed.y !== 1 ? 's' : ''}`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94A3B8' } },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, font: { size: 11 }, color: '#94A3B8' },
                    grid:  { color: '#E2E8F0' },
                },
            },
        },
    });

    // Animar contadores
    if (window.LegalUI) window.LegalUI.animateCounters();
}());
</script>
