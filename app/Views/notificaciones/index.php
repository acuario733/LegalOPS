<?php declare(strict_types=1); $estados = ['pendiente', 'leida']; $severidades = ['info', 'warning', 'critica']; ?>
<div data-feedback hidden></div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="/notificaciones">
            <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="">Todos</option><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= ($filters['estado'] ?? '') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Severidad</label><select class="form-select" name="severidad"><option value="">Todas</option><?php foreach ($severidades as $severidad): ?><option value="<?= e($severidad) ?>" <?= ($filters['severidad'] ?? '') === $severidad ? 'selected' : '' ?>><?= e($severidad) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
</div>
<div class="card" data-module="notificaciones">
    <div class="card-header"><h2 class="card-title">Alertas generadas</h2></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Notificacion</th><th>Origen</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($notificaciones['items'] as $item): ?>
                <?php $severity = (string) ($item['severidad'] ?? 'info'); ?>
                <tr data-notification-state="<?= e((string) $item['estado']) ?>">
                    <td><strong><?= e($item['titulo']) ?></strong><div class="small text-secondary"><?= e($item['mensaje']) ?></div></td>
                    <td><span class="badge text-bg-<?= $severity === 'critica' ? 'danger' : ($severity === 'warning' ? 'warning' : 'secondary') ?>"><?= e($severity) ?></span><div class="small text-secondary"><?= e($item['origen_tipo']) ?> #<?= (int) $item['origen_id'] ?></div></td>
                    <td><?= e($item['estado']) ?></td>
                    <td class="small text-secondary"><?= e($item['created_at']) ?></td>
                    <td class="text-end">
                        <?php if (!empty($item['origen_url'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($item['origen_url']) ?>"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                        <?php if (($item['estado'] ?? '') === 'pendiente'): ?><button class="btn btn-sm btn-primary" data-action="/notificaciones/<?= (int) $item['id'] ?>/leer">Leida</button><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($notificaciones['items'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay notificaciones.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-secondary">Total: <?= (int) $notificaciones['total'] ?></div>
</div>
