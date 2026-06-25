<?php declare(strict_types=1); $tipos = ['contrato','demanda','prueba','poder','soporte','otro']; ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header"><h2 class="card-title">Documento</h2></div><div class="card-body">
            <h3 class="h5"><?= e($documento['titulo']) ?></h3>
            <p class="text-secondary mb-2"><?= e($documento['descripcion'] ?? 'Sin descripcion') ?></p>
            <dl class="row small mb-0">
                <dt class="col-5">Cliente</dt><dd class="col-7"><?= e($documento['cliente_nombre'] ?? 'Sin cliente') ?></dd>
                <dt class="col-5">Caso</dt><dd class="col-7"><?= e($documento['caso_titulo'] ?? 'Sin caso') ?></dd>
                <dt class="col-5">Tipo</dt><dd class="col-7"><?= e($documento['tipo_documental'] ?? 'Sin tipo') ?></dd>
                <dt class="col-5">Version actual</dt><dd class="col-7">v<?= e((string) ($documento['version_numero'] ?? '0')) ?></dd>
                <dt class="col-5">Checksum</dt><dd class="col-7"><code><?= e(mb_substr((string) ($documento['checksum_sha256'] ?? ''), 0, 16)) ?></code></dd>
            </dl>
            <a class="btn btn-primary mt-3" href="/documentos/<?= (int) $documento['id'] ?>/descargar"><i class="bi bi-download"></i> Descargar actual</a>
        </div></div>

        <div class="card"><div class="card-header"><h2 class="card-title">Nueva version</h2></div><div class="card-body">
            <form action="/documentos/<?= (int) $documento['id'] ?>/versiones" method="post" enctype="multipart/form-data" data-ajax-form>
                <div class="mb-3"><label class="form-label">Archivo</label><input class="form-control" type="file" name="archivo" required></div>
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-upload"></i> Versionar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header"><h2 class="card-title">Editar metadata</h2></div><div class="card-body">
            <form action="/documentos/<?= (int) $documento['id'] ?>" method="post" data-ajax-form>
                <input type="hidden" name="_method" value="PATCH">
                <div class="row g-2"><div class="col-md-6"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id"><option value="">Segun caso o gasto</option><?php foreach ($clientes as $cliente): ?><option value="<?= (int) $cliente['id'] ?>" <?= (int) ($documento['cliente_id'] ?? 0) === (int) $cliente['id'] ? 'selected' : '' ?>><?= e($cliente['nombre_razon_social']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Caso</label><select class="form-select" name="caso_id"><option value="">Sin caso</option><?php foreach ($casos as $caso): ?><option value="<?= (int) $caso['id'] ?>" <?= (int) ($documento['caso_id'] ?? 0) === (int) $caso['id'] ? 'selected' : '' ?>><?= e($caso['titulo']) ?></option><?php endforeach; ?></select></div></div>
                <div class="row g-2 mt-1"><div class="col-md-6"><label class="form-label">Gasto</label><select class="form-select" name="gasto_id"><option value="">Sin gasto</option><?php foreach ($gastos as $gasto): ?><option value="<?= (int) $gasto['id'] ?>" <?= (int) ($documento['gasto_id'] ?? 0) === (int) $gasto['id'] ? 'selected' : '' ?>><?= e($gasto['concepto']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Tipo</label><select class="form-select" name="tipo_documental"><option value="">Sin tipo</option><?php foreach ($tipos as $tipo): ?><option value="<?= e($tipo) ?>" <?= ($documento['tipo_documental'] ?? '') === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option><?php endforeach; ?></select></div></div>
                <div class="mt-2"><label class="form-label">Titulo</label><input class="form-control" name="titulo" value="<?= e($documento['titulo']) ?>" required></div>
                <div class="row g-2 mt-1"><div class="col-md-4"><label class="form-label">Visible portal</label><select class="form-select" name="visible_portal"><option value="0">No</option><option value="1" <?= (int) $documento['visible_portal'] === 1 ? 'selected' : '' ?>>Si</option></select></div><div class="col-md-8"><label class="form-label">Descripcion</label><input class="form-control" name="descripcion" value="<?= e($documento['descripcion'] ?? '') ?>"></div></div>
                <button class="btn btn-primary mt-3" type="submit">Guardar</button>
            </form>
            <button class="btn btn-outline-danger mt-3" data-action="/documentos/<?= (int) $documento['id'] ?>/eliminar" data-confirm="Eliminar documento?">Eliminar</button>
        </div></div>

        <div class="card"><div class="card-header"><h2 class="card-title">Historial de versiones</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Version</th><th>Archivo</th><th>MIME</th><th>Checksum</th><th></th></tr></thead><tbody>
        <?php foreach ($documento['versiones'] as $version): ?><tr><td><span class="badge text-bg-secondary">v<?= (int) $version['version_numero'] ?></span></td><td><?= e($version['nombre_original']) ?><div class="small text-secondary"><?= number_format(((int) $version['size_bytes']) / 1024, 1) ?> KB</div></td><td><?= e($version['mime_detectado']) ?></td><td><code><?= e(mb_substr((string) $version['checksum_sha256'], 0, 20)) ?></code></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/documentos/<?= (int) $documento['id'] ?>/versiones/<?= (int) $version['id'] ?>/descargar"><i class="bi bi-download"></i></a></td></tr><?php endforeach; ?>
        <?php if ($documento['versiones'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay versiones.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    </div>
</div>
