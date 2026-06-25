<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="importaciones">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Previsualizar CSV</h2></div>
            <div class="card-body">
                <form action="/importaciones/preview" method="post" enctype="multipart/form-data" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo" required><option value="clientes">Clientes</option><option value="casos">Casos</option></select></div>
                    <div class="mb-3"><label class="form-label">Archivo CSV</label><input class="form-control" type="file" name="archivo" accept=".csv,text/csv" required></div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Previsualizar</button>
                </form>
                <hr>
                <div class="small text-secondary">
                    <div><strong>Clientes:</strong> nombre_razon_social,tipo_persona,tipo_documento,numero_documento,email,telefono</div>
                    <div><strong>Casos:</strong> cliente_id,titulo,radicado,prioridad,estado</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Importaciones recientes</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Archivo</th><th>Tipo</th><th>Filas</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($importaciones as $importacion): ?>
                        <tr>
                            <td><strong><?= e($importacion['nombre_original'] ?? 'CSV') ?></strong><div class="small text-secondary"><?= e($importacion['created_at']) ?></div></td>
                            <td><?= e($importacion['tipo']) ?></td>
                            <td>
                                <?= (int) $importacion['filas_validas'] ?> validas / <?= (int) $importacion['filas_error'] ?> errores
                                <?php $errores = json_decode((string) ($importacion['errores_json'] ?? '[]'), true); ?>
                                <?php if (is_array($errores) && $errores !== []): ?>
                                    <details class="small mt-1"><summary>Ver errores</summary><?php foreach (array_slice($errores, 0, 10) as $error): ?><div>Fila <?= (int) ($error['fila'] ?? 0) ?>: <?= e((string) ($error['error'] ?? 'Error')) ?></div><?php endforeach; ?></details>
                                <?php endif; ?>
                            </td>
                            <td><?= e($importacion['estado']) ?></td>
                            <td class="text-end">
                                <?php if (($importacion['estado'] ?? '') === 'previsualizada' && (int) $importacion['filas_error'] === 0): ?>
                                    <button class="btn btn-sm btn-primary" data-action="/importaciones/<?= (int) $importacion['id'] ?>/confirmar" data-confirm="Confirmar esta importacion?">Confirmar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($importaciones === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay importaciones recientes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
