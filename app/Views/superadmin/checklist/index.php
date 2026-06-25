<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="checklist-owner">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Nuevo checklist</h2></div>
            <div class="card-body">
                <form action="/superadmin/checklist" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" value="Checklist owner fase 05"></div>
                    <div class="mb-3"><label class="form-label">Firma</label><select class="form-select" name="firma_id"><option value="">Global</option><?php foreach ($firmas as $firma): ?><option value="<?= (int) $firma['id'] ?>"><?= e($firma['nombre']) ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-primary" type="submit">Crear checklist</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Checklists</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Titulo</th><th>Firma</th><th>Estado</th><th>Decision</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($checklists as $checklist): ?>
                        <tr><td><strong><?= e($checklist['titulo']) ?></strong><div class="small text-secondary"><?= e($checklist['created_at']) ?></div></td><td><?= e($checklist['firma_nombre'] ?? 'Global') ?></td><td><?= e($checklist['estado']) ?></td><td><?= e($checklist['decision'] ?? '') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/superadmin/checklist/<?= (int) $checklist['id'] ?>">Ver</a></td></tr>
                    <?php endforeach; ?>
                    <?php if ($checklists === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay checklists.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
