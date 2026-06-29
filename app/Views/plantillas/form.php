<?php
declare(strict_types=1);
$isEdit = is_array($template ?? null);
$used = $isEdit ? (array) ($template['variables_usadas'] ?? []) : [];
?>
<div data-feedback hidden></div>
<form id="template-form" data-template-id="<?= $isEdit ? (int) $template['id'] : '' ?>">
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3"><label class="form-label" for="template-name">Nombre de la plantilla</label><input id="template-name" name="nombre" class="form-control" maxlength="200" required value="<?= e($template['nombre'] ?? '') ?>"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="template-category">Categoria</label><select id="template-category" name="categoria" class="form-select"><option value="">Sin categoria</option><?php foreach ($categorias as $categoria): ?><option value="<?= e($categoria) ?>" <?= ($template['categoria'] ?? '') === $categoria ? 'selected' : '' ?>><?= e($categoria) ?></option><?php endforeach; ?><option value="__new">Nueva categoria...</option></select></div>
                        <div class="col-md-6"><label class="form-label" for="template-category-new">Nueva categoria</label><input id="template-category-new" name="categoria_nueva" class="form-control" maxlength="100" disabled></div>
                    </div>
                    <div class="mb-3 mt-3"><label class="form-label" for="template-description">Descripcion</label><textarea id="template-description" name="descripcion" class="form-control" rows="2"><?= e($template['descripcion'] ?? '') ?></textarea></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="template-active" name="activo" value="1" <?= (int) ($template['activo'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="template-active">Activa</label></div>
                    <label class="form-label" for="template-content">Contenido</label>
                    <textarea id="template-content" name="contenido" class="form-control" rows="18"><?= e($template['contenido'] ?? '<p>Señores:</p><p>Referencia: {{caso.titulo}}</p>') ?></textarea>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="/plantillas">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar plantilla</button>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header"><h2 class="card-title">Variables disponibles</h2></div><div class="accordion accordion-flush" id="variablesAccordion">
                <?php foreach ($variables as $group => $vars): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#vars-<?= e($group) ?>"><?= e(ucfirst($group)) ?></button></h3>
                        <div id="vars-<?= e($group) ?>" class="accordion-collapse collapse" data-bs-parent="#variablesAccordion"><div class="accordion-body p-2">
                            <?php foreach ($vars as $key => $description): ?><button type="button" class="btn btn-sm btn-outline-secondary d-block w-100 text-start mb-2" data-template-variable="<?= e($group . '.' . $key) ?>"><code>{{<?= e($group . '.' . $key) ?>}}</code><br><span class="small"><?= e($description) ?></span></button><?php endforeach; ?>
                        </div></div>
                    </div>
                <?php endforeach; ?>
            </div></div>
            <div class="card"><div class="card-header"><h2 class="card-title">Variables detectadas</h2></div><div class="card-body"><div id="template-detected-vars"><?php foreach ($used as $var): ?><span class="badge text-bg-light border me-1 mb-1">{{<?= e($var) ?>}}</span><?php endforeach; ?></div></div></div>
        </div>
    </div>
</form>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/assets/js/template-editor.js"></script>
