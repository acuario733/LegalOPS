<?php

declare(strict_types=1);

$initialFields = $form['campos'] ?? [];

$fieldTypesMeta = [
    'texto'    => ['icon' => 'bi-fonts',              'label' => 'Texto'],
    'email'    => ['icon' => 'bi-envelope',           'label' => 'Email'],
    'telefono' => ['icon' => 'bi-telephone',          'label' => 'Teléfono'],
    'select'   => ['icon' => 'bi-ui-checks-grid',     'label' => 'Selección'],
    'textarea' => ['icon' => 'bi-text-left',          'label' => 'Área de texto'],
    'fecha'    => ['icon' => 'bi-calendar3',          'label' => 'Fecha'],
    'checkbox' => ['icon' => 'bi-check2-square',      'label' => 'Checkbox'],
];
?>
<style>
/* ── INTAKE BUILDER — DESIGN SYSTEM ─────────────────────────────── */
.intake-builder-grid {
    display: grid;
    grid-template-columns: 260px minmax(360px,1fr) 300px;
    gap: var(--space-4);
    align-items: start;
}
.builder-panel {
    background:    var(--color-surface);
    border:        1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow:    var(--shadow-card);
    padding:       var(--space-5);
}
.builder-panel-title {
    font-size:   var(--font-size-sm);
    font-weight: 600;
    color:       var(--color-navy);
    margin-bottom: var(--space-1);
}
.builder-panel-subtitle {
    font-size:  var(--font-size-xs);
    color:      var(--color-text-muted);
    margin-bottom: var(--space-4);
    line-height: 1.4;
}

/* Tipos de campo */
.field-type-btn {
    width:         100%;
    text-align:    left;
    background:    var(--color-surface);
    border:        1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding:       var(--space-3) var(--space-3);
    margin-bottom: var(--space-2);
    display:       flex;
    align-items:   center;
    gap:           var(--space-3);
    font-size:     var(--font-size-sm);
    font-weight:   500;
    color:         var(--color-text-primary);
    cursor:        pointer;
    transition:    box-shadow 0.15s, border-color 0.15s;
}
.field-type-btn:hover {
    border-color: var(--color-green);
    box-shadow:   var(--shadow-hover);
}
.field-type-btn .bi {
    font-size:  16px;
    color:      var(--color-text-secondary);
    flex-shrink:0;
}

/* Campos en el canvas */
.builder-field {
    display:       flex;
    align-items:   center;
    gap:           var(--space-3);
    border:        1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding:       var(--space-3) var(--space-4);
    margin-bottom: var(--space-2);
    background:    var(--color-surface);
    cursor:        pointer;
    transition:    border-color 0.15s, background 0.15s;
}
.builder-field:hover   { border-color: var(--color-border-dark); }
.builder-field.is-selected {
    border-color: var(--color-green);
    background:   #F0FAF5;
}
.builder-field .drag-handle {
    cursor:      grab;
    color:       var(--color-text-muted);
    font-size:   18px;
    flex-shrink: 0;
}
.builder-field .drag-handle:active { cursor: grabbing; }
.builder-field .field-icon {
    font-size:   16px;
    color:       var(--color-text-secondary);
    flex-shrink: 0;
}
.builder-field .field-name {
    flex:        1;
    font-size:   var(--font-size-sm);
    font-weight: 500;
    overflow:    hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.builder-field .field-actions {
    display:    flex;
    gap:        var(--space-1);
    flex-shrink:0;
}
.builder-field-action {
    width:          28px;
    height:         28px;
    border:         none;
    border-radius:  var(--radius-sm);
    display:        flex;
    align-items:    center;
    justify-content:center;
    font-size:      13px;
    cursor:         pointer;
    transition:     background 0.15s;
}
.builder-field-action.edit   { background: var(--color-green-light); color: var(--color-green); }
.builder-field-action.delete { background: var(--color-danger-light); color: var(--color-danger); }
.builder-field-action:hover  { filter: brightness(0.92); }

/* Drop zone */
.builder-drop-zone {
    border:        2px dashed var(--color-border-dark);
    border-radius: var(--radius-sm);
    padding:       var(--space-5);
    text-align:    center;
    color:         var(--color-text-muted);
    font-size:     var(--font-size-sm);
    margin-top:    var(--space-3);
    transition:    border-color 0.15s;
}
.builder-drop-zone.drag-over { border-color: var(--color-green); background: var(--color-green-light); }

/* Preview vivo */
.builder-preview {
    border-top:    1px solid var(--color-border);
    margin-top:    var(--space-5);
    padding-top:   var(--space-5);
}
.preview-field-label {
    font-size:     var(--font-size-xs);
    font-weight:   600;
    color:         var(--color-text-secondary);
    margin-bottom: var(--space-1);
    display:       block;
}

/* Opciones de select */
.option-item {
    display:       flex;
    align-items:   center;
    gap:           var(--space-2);
    margin-bottom: var(--space-2);
}
.option-item .drag-handle { font-size: 14px; }
.option-item input        { flex: 1; }
.option-remove {
    width:       28px; height: 28px; border: none; border-radius: var(--radius-sm);
    background:  var(--color-danger-light); color: var(--color-danger);
    cursor:      pointer; display: flex; align-items: center; justify-content: center;
    font-size:   13px; flex-shrink: 0;
}

@media (max-width:1100px) {
    .intake-builder-grid { grid-template-columns: 220px 1fr; }
    .builder-inspector   { grid-column: 1 / -1; }
}
@media (max-width:767.98px) {
    .intake-builder-grid { grid-template-columns: 1fr; }
}
</style>

<!-- HEADER DE PÁGINA -->
<div class="page-header mb-4">
    <div>
        <h1 class="page-title">Constructor de formulario</h1>
        <p class="page-subtitle">Diseña el formulario de intake para capturar prospectos</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/intake" class="btn btn-ghost">
            <i class="bi bi-arrow-left"></i>Volver
        </a>
        <?php if ($form !== null): ?>
        <a class="btn btn-outline-primary" target="_blank" href="/intake/<?= e($form['id']) ?>/preview">
            <i class="bi bi-eye"></i>Vista previa
        </a>
        <?php endif; ?>
        <button class="btn btn-primary" type="button" id="save-intake">
            <i class="bi bi-floppy"></i>Guardar formulario
        </button>
    </div>
</div>

<!-- METADATOS DEL FORMULARIO -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="form-name">Nombre interno</label>
                <input id="form-name" class="form-control" maxlength="100" value="<?= e($form['nombre'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="form-title">Título público</label>
                <input id="form-title" class="form-control" maxlength="200" value="<?= e($form['titulo'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="form-notify">Notificar a</label>
                <input id="form-notify" class="form-control" placeholder="equipo@firma.com" value="<?= e($form['notificar_emails'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="form-description">Descripción</label>
                <textarea id="form-description" class="form-control" rows="2"><?= e($form['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="form-success">Mensaje de éxito</label>
                <input id="form-success" class="form-control" value="<?= e($form['mensaje_exito'] ?? 'Gracias. Recibimos tu información.') ?>">
            </div>
        </div>
    </div>
</div>

<!-- BUILDER GRID (3 columnas) -->
<div id="intake-builder" class="intake-builder-grid"
     data-form-id="<?= e($form['id'] ?? '') ?>"
     data-fields="<?= e(json_encode($initialFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

    <!-- COL 1: Tipos de campo -->
    <aside class="builder-panel">
        <h2 class="builder-panel-title">Tipos de campo</h2>
        <p class="builder-panel-subtitle">
            Haz clic en el tipo de campo que deseas agregar al formulario.
        </p>
        <?php foreach ($fieldTypesMeta as $type => $meta): ?>
        <button type="button" class="field-type-btn" data-add-field="<?= e($type) ?>">
            <i class="bi <?= e($meta['icon']) ?>"></i>
            <?= e($meta['label']) ?>
        </button>
        <?php endforeach; ?>
    </aside>

    <!-- COL 2: Canvas de campos -->
    <section class="builder-panel">
        <h2 class="builder-panel-title">Campos del formulario</h2>
        <p class="builder-panel-subtitle">
            Arrastra para reordenar. Haz clic en un campo para editar sus propiedades.
        </p>
        <div id="intake-canvas"></div>
        <div class="builder-drop-zone" id="builder-drop-hint">
            <i class="bi bi-plus-circle me-2"></i>
            Agrega campos desde el panel izquierdo
        </div>
    </section>

    <!-- COL 3: Propiedades -->
    <aside class="builder-panel builder-inspector">
        <h2 class="builder-panel-title">Propiedades</h2>

        <div id="intake-empty-properties" class="text-center py-5">
            <i class="bi bi-cursor-text" style="font-size:2rem;color:var(--color-text-muted)"></i>
            <p class="mt-2" style="font-size:var(--font-size-sm);color:var(--color-text-muted)">
                Selecciona un campo para editar sus propiedades
            </p>
        </div>

        <div id="intake-properties" hidden>
            <div class="mb-3">
                <label class="form-label" for="field-label">Etiqueta</label>
                <input id="field-label" class="form-control" maxlength="160">
            </div>
            <div class="mb-3">
                <label class="form-label" for="field-placeholder">Placeholder</label>
                <input id="field-placeholder" class="form-control" maxlength="200">
            </div>
            <div class="mb-3 d-flex align-items-center gap-3">
                <label class="form-label mb-0" for="field-required">Campo obligatorio</label>
                <div class="form-check form-switch mb-0">
                    <input id="field-required" class="form-check-input" type="checkbox" role="switch">
                </div>
            </div>
            <div id="field-options-wrap" hidden>
                <label class="form-label">Opciones</label>
                <div id="field-options-list"></div>
                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" id="add-option-btn">
                    <i class="bi bi-plus"></i>Agregar opción
                </button>
            </div>
        </div>

        <!-- Preview live -->
        <div class="builder-preview">
            <h3 class="builder-panel-title">Vista previa</h3>
            <div id="intake-live-preview">
                <p style="font-size:var(--font-size-xs);color:var(--color-text-muted)">
                    Selecciona un campo para ver la vista previa
                </p>
            </div>
        </div>
    </aside>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable.js/1.15.0/Sortable.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="/assets/js/intake-builder.js"></script>
