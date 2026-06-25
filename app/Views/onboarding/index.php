<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="card mb-4" data-module="onboarding">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="flex-grow-1">
                <div class="progress" role="progressbar" aria-valuenow="<?= (int) $onboarding['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: <?= (int) $onboarding['percent'] ?>%"><?= (int) $onboarding['percent'] ?>%</div>
                </div>
            </div>
            <form action="/onboarding/actualizar" method="post" data-ajax-form><button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-clockwise"></i> Actualizar</button></form>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2 class="card-title">Activacion de la firma</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Paso</th><th>Estado</th><th>Completado</th></tr></thead>
            <tbody>
            <?php foreach ($onboarding['steps'] as $step): ?>
                <tr><td><strong><?= e($step['titulo']) ?></strong><div class="small text-secondary"><?= e($step['codigo']) ?></div></td><td><span class="badge text-bg-<?= ($step['estado'] ?? '') === 'completado' ? 'success' : 'secondary' ?>"><?= e($step['estado'] ?? 'pendiente') ?></span></td><td><?= e($step['completed_at'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
