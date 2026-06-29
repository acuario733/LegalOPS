<?php

declare(strict_types=1);

$fields = is_array($form['campos'] ?? null) ? $form['campos'] : [];
?>
<div class="row justify-content-center">
    <div class="col-xl-9">
        <header class="mb-4">
            <div class="brand-wordmark fs-3 mb-4"><?= e($form['firma_nombre'] ?? 'LegalOPS Cloud') ?></div>
            <h1 class="display-6 fw-bold mb-3"><?= e($form['titulo']) ?></h1>
            <?php if (!empty($form['descripcion'])): ?><p class="lead text-muted-legal"><?= e($form['descripcion']) ?></p><?php endif; ?>
        </header>
        <section class="public-panel p-4 p-md-5">
            <?php if (!empty($preview)): ?><div class="alert alert-warning">Vista previa: el envío está deshabilitado.</div><?php endif; ?>
            <form id="public-intake-form" action="/intake/<?= e($form['firma_slug'] ?? '') ?>/<?= e($form['slug']) ?>" method="post" <?= !empty($preview) ? 'data-preview="1"' : '' ?>>
                <div class="row g-4">
                    <?php foreach ($fields as $field): ?>
                        <?php
                        $key = (string) ($field['key'] ?? '');
                        $type = (string) ($field['type'] ?? 'texto');
                        $required = !empty($field['requerido']);
                        $column = in_array($type, ['textarea'], true) ? 'col-12' : 'col-md-6';
                        ?>
                        <div class="<?= $column ?>">
                            <?php if ($type === 'checkbox'): ?>
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" value="1" id="field-<?= e($key) ?>" name="<?= e($key) ?>" <?= $required ? 'required' : '' ?>>
                                    <label class="form-check-label" for="field-<?= e($key) ?>"><?= e($field['etiqueta']) ?><?= $required ? ' *' : '' ?></label>
                                </div>
                            <?php else: ?>
                                <label class="form-label" for="field-<?= e($key) ?>"><?= e($field['etiqueta']) ?><?= $required ? ' *' : '' ?></label>
                                <?php if ($type === 'textarea'): ?>
                                    <textarea class="form-control" rows="5" id="field-<?= e($key) ?>" name="<?= e($key) ?>" placeholder="<?= e($field['placeholder'] ?? '') ?>" <?= $required ? 'required' : '' ?>></textarea>
                                <?php elseif ($type === 'select'): ?>
                                    <select class="form-select" id="field-<?= e($key) ?>" name="<?= e($key) ?>" <?= $required ? 'required' : '' ?>><option value="">Selecciona una opción</option><?php foreach ((array) ($field['opciones'] ?? []) as $option): ?><option value="<?= e($option) ?>"><?= e($option) ?></option><?php endforeach; ?></select>
                                <?php else: ?>
                                    <?php $htmlType = match ($type) {'email' => 'email', 'telefono' => 'tel', 'fecha' => 'date', default => 'text'}; ?>
                                    <input class="form-control" type="<?= $htmlType ?>" id="field-<?= e($key) ?>" name="<?= e($key) ?>" placeholder="<?= e($field['placeholder'] ?? '') ?>" <?= $required ? 'required' : '' ?>>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-legal btn-lg w-100 mt-4" type="submit" <?= !empty($preview) ? 'disabled' : '' ?>>Enviar solicitud</button>
            </form>
            <div class="success-panel rounded-3 p-4 mt-4" id="intake-success" role="status" hidden>
                <h2 class="h5 mb-1">Gracias. Recibimos tu información.</h2>
                <p class="mb-0" data-success-message></p>
            </div>
            <div class="alert alert-danger mt-4" id="intake-error" role="alert" hidden></div>
        </section>
    </div>
</div>
<script>
document.getElementById('public-intake-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (event.currentTarget.dataset.preview === '1') return;
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        event.currentTarget.querySelectorAll('input[type="checkbox"]').forEach(input => data[input.name] = input.checked);
        const response = await fetch(event.currentTarget.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify(data)
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'No fue posible enviar el formulario.');
        event.currentTarget.hidden = true;
        const success = document.getElementById('intake-success');
        success.querySelector('[data-success-message]').textContent = payload.data.mensaje_exito;
        success.hidden = false;
    } catch (error) {
        const alert = document.getElementById('intake-error');
        alert.textContent = error.message;
        alert.hidden = false;
        button.disabled = false;
    }
});
</script>
