<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nuevo evento</h2></div><div class="card-body">
            <form action="/casos/<?= (int) $caso['id'] ?>/timeline" method="post" data-ajax-form>
                <div class="mb-2"><label class="form-label">Fecha</label><input class="form-control" name="fecha_evento" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="mb-2"><label class="form-label">Tipo</label><input class="form-control" name="tipo_evento" value="actuacion" required></div>
                <div class="mb-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" required></div>
                <div class="mb-2"><label class="form-label">Contenido publico</label><textarea class="form-control" name="contenido_publico" rows="2"></textarea></div>
                <div class="mb-2"><label class="form-label">Nota interna</label><textarea class="form-control" name="contenido_interno" rows="3"></textarea></div>
                <div class="mb-2"><label class="form-label">Visibilidad</label><select class="form-select" name="visibilidad"><option value="interna">Interna</option><?php if ($canPublish): ?><option value="publica">Publica</option><?php endif; ?></select></div>
                <input type="hidden" name="estado" value="activo"><input type="hidden" name="critico" value="0">
                <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="critico" value="1"><span class="form-check-label">Evento critico</span></label>
                <button class="btn btn-primary" type="submit">Crear evento</button>
            </form>
        </div></div>
    </div>
    <div class="col-xl-8">
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="card-title mb-0"><?= e($caso['titulo']) ?></h2><a href="/casos/<?= (int) $caso['id'] ?>" class="btn btn-sm btn-outline-secondary">Caso</a></div><div class="card-body">
            <?php foreach ($eventos as $evento): ?>
                <div class="border-start border-3 ps-3 pb-3 mb-3">
                    <div class="d-flex justify-content-between gap-3"><div><strong><?= e($evento['titulo']) ?></strong><div class="small text-secondary"><?= e($evento['fecha_evento']) ?> · <?= e($evento['tipo_evento']) ?> · <?= e($evento['visibilidad']) ?><?= (int) $evento['critico'] === 1 ? ' · critico' : '' ?></div></div><button class="btn btn-sm btn-outline-danger" data-action="/casos/<?= (int) $caso['id'] ?>/timeline/<?= (int) $evento['id'] ?>/eliminar" data-confirm="Eliminar evento?">Eliminar</button></div>
                    <?php if (($evento['contenido_publico'] ?? '') !== ''): ?><p class="mb-1 mt-2"><?= e($evento['contenido_publico']) ?></p><?php endif; ?>
                    <?php if (($evento['contenido_interno'] ?? '') !== ''): ?><div class="small text-secondary"><?= e($evento['contenido_interno']) ?></div><?php endif; ?>
                    <details class="mt-2"><summary class="small">Editar</summary><form class="mt-2" action="/casos/<?= (int) $caso['id'] ?>/timeline/<?= (int) $evento['id'] ?>" method="post" data-ajax-form><input type="hidden" name="_method" value="PATCH"><input class="form-control form-control-sm mb-1" name="fecha_evento" type="date" value="<?= e(substr((string) $evento['fecha_evento'], 0, 10)) ?>"><input class="form-control form-control-sm mb-1" name="tipo_evento" value="<?= e($evento['tipo_evento']) ?>"><input class="form-control form-control-sm mb-1" name="titulo" value="<?= e($evento['titulo']) ?>"><textarea class="form-control form-control-sm mb-1" name="contenido_publico"><?= e($evento['contenido_publico'] ?? '') ?></textarea><textarea class="form-control form-control-sm mb-1" name="contenido_interno"><?= e($evento['contenido_interno'] ?? '') ?></textarea><select class="form-select form-select-sm mb-1" name="visibilidad"><option value="interna" <?= $evento['visibilidad'] === 'interna' ? 'selected' : '' ?>>Interna</option><?php if ($canPublish || $evento['visibilidad'] === 'publica'): ?><option value="publica" <?= $evento['visibilidad'] === 'publica' ? 'selected' : '' ?>>Publica</option><?php endif; ?></select><input type="hidden" name="estado" value="activo"><input type="hidden" name="critico" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="critico" value="1" <?= (int) $evento['critico'] === 1 ? 'checked' : '' ?>><span class="form-check-label">Critico</span></label><button class="btn btn-sm btn-primary mt-2">Guardar</button></form></details>
                </div>
            <?php endforeach; ?>
            <?php if ($eventos === []): ?><div class="text-center text-secondary py-4">No hay eventos en la linea de tiempo.</div><?php endif; ?>
        </div></div>
    </div>
</div>
