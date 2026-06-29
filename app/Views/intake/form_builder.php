<?php

declare(strict_types=1);

$initialFields = $form['campos'] ?? [];
?>
<style>
.intake-builder-grid{display:grid;grid-template-columns:220px minmax(360px,1fr) 320px;gap:1rem;align-items:start}
.builder-panel{background:#fff;border:1px solid #dbe3ec;border-radius:10px;padding:1rem}
.field-type-button{width:100%;text-align:left;margin-bottom:.5rem}
.builder-field{display:flex;align-items:center;gap:.75rem;border:1px solid #d8e0e9;border-radius:9px;padding:.8rem;margin-bottom:.65rem;background:#fff;cursor:pointer}
.builder-field.is-selected{border-color:#198754;background:#f3fbf7}.builder-field .drag-handle{cursor:grab;color:#667085}
.builder-preview{border-top:1px solid #e4e9ef;margin-top:1rem;padding-top:1rem}
@media(max-width:1100px){.intake-builder-grid{grid-template-columns:200px 1fr}.builder-inspector{grid-column:1/-1}}
@media(max-width:767.98px){.intake-builder-grid{grid-template-columns:1fr}}
</style>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <a href="/intake" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <div class="d-flex gap-2">
        <?php if ($form !== null): ?><a class="btn btn-outline-primary" target="_blank" href="/intake/<?= e($form['id']) ?>/preview"><i class="bi bi-eye me-1"></i>Vista previa</a><?php endif; ?>
        <button class="btn btn-success" type="button" id="save-intake"><i class="bi bi-floppy me-1"></i>Guardar formulario</button>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><label class="form-label" for="form-name">Nombre interno</label><input id="form-name" class="form-control" maxlength="100" value="<?= e($form['nombre'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="form-title">Título público</label><input id="form-title" class="form-control" maxlength="200" value="<?= e($form['titulo'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="form-notify">Notificar a</label><input id="form-notify" class="form-control" placeholder="equipo@firma.com" value="<?= e($form['notificar_emails'] ?? '') ?>"></div>
    <div class="col-md-8"><label class="form-label" for="form-description">Descripción</label><textarea id="form-description" class="form-control" rows="2"><?= e($form['descripcion'] ?? '') ?></textarea></div>
    <div class="col-md-4"><label class="form-label" for="form-success">Mensaje de éxito</label><input id="form-success" class="form-control" value="<?= e($form['mensaje_exito'] ?? 'Gracias. Recibimos tu información.') ?>"></div>
</div>
<div id="intake-builder" class="intake-builder-grid" data-form-id="<?= e($form['id'] ?? '') ?>" data-fields="<?= e(json_encode($initialFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <aside class="builder-panel">
        <h2 class="h6">Tipos de campo</h2><p class="small text-secondary">Agrega un campo al formulario.</p>
        <?php foreach ($fieldTypes as $type): ?>
            <button type="button" class="btn btn-outline-secondary field-type-button" data-add-field="<?= e($type) ?>"><i class="bi bi-plus-lg me-2"></i><?= e(ucfirst($type)) ?></button>
        <?php endforeach; ?>
    </aside>
    <section class="builder-panel">
        <h2 class="h6">Campos del formulario</h2><p class="small text-secondary">Arrastra para reordenar y selecciona para editar.</p>
        <div id="intake-canvas"></div>
    </section>
    <aside class="builder-panel builder-inspector">
        <h2 class="h6">Propiedades</h2>
        <div id="intake-empty-properties" class="text-secondary small py-4">Selecciona un campo para editar sus propiedades.</div>
        <div id="intake-properties" hidden>
            <label class="form-label" for="field-label">Etiqueta</label><input id="field-label" class="form-control mb-3" maxlength="160">
            <label class="form-label" for="field-placeholder">Placeholder</label><input id="field-placeholder" class="form-control mb-3" maxlength="200">
            <div class="form-check form-switch mb-3"><input id="field-required" class="form-check-input" type="checkbox"><label class="form-check-label" for="field-required">Campo obligatorio</label></div>
            <div id="field-options-wrap" hidden><label class="form-label" for="field-options">Opciones, una por línea</label><textarea id="field-options" class="form-control" rows="5"></textarea></div>
        </div>
        <div class="builder-preview"><h3 class="h6">Vista previa</h3><div id="intake-live-preview"></div></div>
    </aside>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable.js/1.15.0/Sortable.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="/assets/js/intake-builder.js"></script>
