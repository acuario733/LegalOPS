<?php
declare(strict_types=1);
$selected = (string) ($filters['categoria'] ?? '');
?>
<div data-feedback hidden></div>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <form class="d-flex gap-2" method="get">
            <select class="form-select" name="categoria" onchange="this.form.submit()">
                <option value="">Todas las categorias</option>
                <?php foreach ($categorias as $categoria): ?><option value="<?= e($categoria) ?>" <?= $selected === $categoria ? 'selected' : '' ?>><?= e($categoria) ?></option><?php endforeach; ?>
            </select>
        </form>
        <a class="btn btn-primary" href="/plantillas/nueva"><i class="bi bi-plus-lg me-1"></i>Nueva plantilla</a>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Nombre</th><th>Categoria</th><th>Variables usadas</th><th>Ultima modificacion</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($templates as $template): ?>
                <tr>
                    <td><strong><?= e($template['nombre']) ?></strong><br><span class="text-secondary small"><?= e($template['descripcion'] ?? '') ?></span></td>
                    <td><?= e($template['categoria'] ?? 'Sin categoria') ?></td>
                    <td>
                        <?php foreach ((array) ($template['variables_usadas'] ?? []) as $var): ?><span class="badge text-bg-light border me-1">{{<?= e($var) ?>}}</span><?php endforeach; ?>
                    </td>
                    <td><?= e($template['updated_at'] ?? '') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="/plantillas/<?= (int) $template['id'] ?>/editar">Editar</a>
                        <button class="btn btn-sm btn-outline-danger" type="button" data-delete-template="<?= (int) $template['id'] ?>">Eliminar</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($templates === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay plantillas registradas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="/assets/js/template-editor.js"></script>
