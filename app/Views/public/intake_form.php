<?php

declare(strict_types=1);

$fields     = is_array($form['campos'] ?? null) ? $form['campos'] : [];
$firmaNombre= $form['firma_nombre'] ?? 'LegalOPS Cloud';
$firmaLetra = strtoupper(mb_substr($firmaNombre, 0, 1));
$formAction = '/intake/' . ($form['firma_slug'] ?? '') . '/' . ($form['slug'] ?? '');
?>
<div class="public-card">
    <!-- Logo y nombre de la firma -->
    <div class="mb-4">
        <div class="firm-logo-circle"><?= e($firmaLetra) ?></div>
        <p class="firm-name"><?= e($firmaNombre) ?></p>
    </div>

    <?php if (!empty($preview)): ?>
    <div class="error-panel mb-4">
        <i class="bi bi-eye me-2"></i>Vista previa — el envío está deshabilitado.
    </div>
    <?php endif; ?>

    <!-- Título y descripción del formulario -->
    <h1 class="public-title"><?= e($form['titulo']) ?></h1>
    <?php if (!empty($form['descripcion'])): ?>
    <p class="public-subtitle"><?= e($form['descripcion']) ?></p>
    <?php endif; ?>

    <!-- Formulario -->
    <form id="public-intake-form"
          action="<?= e($formAction) ?>"
          method="post"
          <?= !empty($preview) ? 'data-preview="1"' : '' ?>>
        <div class="row g-3">
            <?php foreach ($fields as $field): ?>
            <?php
            $key      = (string) ($field['key']       ?? '');
            $type     = (string) ($field['type']      ?? 'texto');
            $required = !empty($field['requerido']);
            $label    = (string) ($field['etiqueta']  ?? '');
            $placeholder = (string) ($field['placeholder'] ?? '');
            $column   = ($type === 'textarea') ? 'col-12' : 'col-md-6';
            ?>
            <div class="<?= $column ?>">
                <?php if ($type === 'checkbox'): ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" value="1"
                           id="field-<?= e($key) ?>" name="<?= e($key) ?>"
                           <?= $required ? 'required' : '' ?>>
                    <label class="form-check-label" for="field-<?= e($key) ?>">
                        <?= e($label) ?><?= $required ? ' <span style="color:#DC2626">*</span>' : '' ?>
                    </label>
                </div>
                <?php elseif ($type === 'textarea'): ?>
                <label class="form-label" for="field-<?= e($key) ?>">
                    <?= e($label) ?><?= $required ? ' <span style="color:#DC2626">*</span>' : '' ?>
                </label>
                <textarea class="form-control" rows="5"
                          id="field-<?= e($key) ?>" name="<?= e($key) ?>"
                          placeholder="<?= e($placeholder) ?>"
                          <?= $required ? 'required' : '' ?>></textarea>
                <?php elseif ($type === 'select'): ?>
                <label class="form-label" for="field-<?= e($key) ?>">
                    <?= e($label) ?><?= $required ? ' <span style="color:#DC2626">*</span>' : '' ?>
                </label>
                <select class="form-select" id="field-<?= e($key) ?>" name="<?= e($key) ?>"
                        <?= $required ? 'required' : '' ?>>
                    <option value="">Selecciona una opción</option>
                    <?php foreach ((array) ($field['opciones'] ?? []) as $option): ?>
                    <option value="<?= e($option) ?>"><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <?php
                $htmlType = match ($type) {
                    'email'    => 'email',
                    'telefono' => 'tel',
                    'fecha'    => 'date',
                    default    => 'text',
                };
                ?>
                <label class="form-label" for="field-<?= e($key) ?>">
                    <?= e($label) ?><?= $required ? ' <span style="color:#DC2626">*</span>' : '' ?>
                </label>
                <input class="form-control" type="<?= $htmlType ?>"
                       id="field-<?= e($key) ?>" name="<?= e($key) ?>"
                       placeholder="<?= e($placeholder) ?>"
                       <?= $required ? 'required' : '' ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Aviso de privacidad -->
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" id="privacy-check" required>
            <label class="form-check-label" for="privacy-check" style="font-size:0.8rem;color:#4A5568;">
                Acepto el <a href="#" class="privacy-link">aviso de privacidad</a>
                y autorizo el tratamiento de mis datos.
            </label>
        </div>

        <button class="btn btn-legal w-100 mt-4" type="submit" <?= !empty($preview) ? 'disabled' : '' ?>>
            <i class="bi bi-send"></i>
            Enviar solicitud
        </button>
    </form>

    <!-- Mensaje de éxito -->
    <div class="success-panel mt-4" id="intake-success" role="status" hidden>
        <div class="success-icon"><i class="bi bi-check-lg"></i></div>
        <p class="success-title">¡Mensaje enviado!</p>
        <p class="success-subtitle mb-0" data-success-message>
            Gracias. Recibimos tu información. Uno de nuestros abogados se pondrá en contacto contigo a la brevedad.
        </p>
    </div>

    <!-- Error -->
    <div class="error-panel mt-4" id="intake-error" role="alert" hidden></div>
</div>

<script>
document.getElementById('public-intake-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (event.currentTarget.dataset.preview === '1') return;

    const form   = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Enviando…';

    try {
        const data = Object.fromEntries(new FormData(form).entries());
        form.querySelectorAll('input[type="checkbox"]').forEach(input => {
            data[input.name] = input.checked;
        });

        const response = await fetch(form.action, {
            method:      'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(data),
        });

        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'No fue posible enviar el formulario.');

        form.hidden = true;
        const successEl = document.getElementById('intake-success');
        const msgEl     = successEl.querySelector('[data-success-message]');
        if (msgEl && payload.data?.mensaje_exito) {
            msgEl.textContent = payload.data.mensaje_exito;
        }
        successEl.hidden = false;

    } catch (error) {
        const errorEl = document.getElementById('intake-error');
        errorEl.textContent = error.message;
        errorEl.hidden = false;
        button.disabled  = false;
        button.innerHTML = '<i class="bi bi-send"></i> Enviar solicitud';
    }
});
</script>
