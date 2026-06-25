<?php declare(strict_types=1); $estados = ['pendiente', 'aprobado', 'fallido']; ?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="checklist-owner">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= e($checklist['titulo']) ?></h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Item</th><th>Estado</th><th>Evaluacion</th></tr></thead>
                    <tbody>
                    <?php foreach ($checklist['items'] as $item): ?>
                        <tr>
                            <td><strong><?= e($item['titulo']) ?></strong><div class="small text-secondary"><?= e($item['codigo']) ?></div></td>
                            <td><?= e($item['estado']) ?></td>
                            <td><form action="/superadmin/checklist/<?= (int) $checklist['id'] ?>/items/<?= (int) $item['id'] ?>" method="post" data-ajax-form class="row g-2"><div class="col-md-3"><select class="form-select form-select-sm" name="estado"><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= $item['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><input class="form-control form-control-sm" name="observacion" placeholder="Observacion" value="<?= e($item['observacion'] ?? '') ?>"></div><div class="col-md-3"><input class="form-control form-control-sm" name="evidencia" placeholder="Evidencia" value="<?= e($item['evidencia'] ?? '') ?>"></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Guardar</button></div></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Decision final</h2></div>
            <div class="card-body">
                <dl class="row small text-secondary">
                    <dt class="col-5">Estado</dt><dd class="col-7"><?= e($checklist['estado']) ?></dd>
                    <dt class="col-5">Decision</dt><dd class="col-7"><?= e($checklist['decision'] ?? '') ?></dd>
                </dl>
                <form action="/superadmin/checklist/<?= (int) $checklist['id'] ?>/decision" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Decision</label><select class="form-select" name="decision"><option value="aprobado">Aprobado</option><option value="rechazado">Rechazado</option></select></div>
                    <div class="mb-3"><label class="form-label">Observacion humana</label><textarea class="form-control" name="observacion" rows="5" required><?= e($checklist['decision_observacion'] ?? '') ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Registrar decision</button>
                </form>
            </div>
        </div>
    </div>
</div>
