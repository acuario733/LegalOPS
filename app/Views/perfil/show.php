<?php

declare(strict_types=1);

$roles = (array) ($perfil['roles'] ?? []);
$verificationState = (string) ($perfil['tarjeta_profesional_verificacion_estado'] ?? '');
$verificationLabel = match ($verificationState) {
    'pendiente' => 'Pendiente',
    'verificada' => 'Verificada',
    'rechazada' => 'Rechazada',
    default => 'Sin tarjeta registrada',
};
$verificationClass = match ($verificationState) {
    'pendiente' => 'text-bg-warning',
    'verificada' => 'text-bg-success',
    'rechazada' => 'text-bg-danger',
    default => 'text-bg-secondary',
};
$maskedDocument = trim((string) (($perfil['tipo_documento_codigo'] ?? '') . ' ' . ($perfil['numero_documento_enmascarado'] ?? '')));
$maskedCard = (string) ($perfil['numero_tarjeta_profesional_enmascarado'] ?? '');
?>
<div data-feedback hidden></div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0"><i class="bi bi-person-circle me-2"></i>Información personal</h2>
            </div>
            <div class="card-body">
                <?php if ($canEdit): ?>
                    <form action="/mi-perfil" method="post" enctype="multipart/form-data" data-ajax-form>
                        <input type="hidden" name="_method" value="PATCH">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="perfil-nombres">Nombres</label>
                                <input class="form-control" id="perfil-nombres" name="nombres" maxlength="160" value="<?= e($perfil['nombres'] ?? $perfil['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="perfil-apellidos">Apellidos</label>
                                <input class="form-control" id="perfil-apellidos" name="apellidos" maxlength="160" value="<?= e($perfil['apellidos'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="perfil-tipo-documento">Tipo de documento</label>
                                <select class="form-select" id="perfil-tipo-documento" name="tipo_documento_id" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($tiposDocumento as $tipo): ?>
                                        <option value="<?= (int) $tipo['id'] ?>" <?= (int) ($perfil['tipo_documento_id'] ?? 0) === (int) $tipo['id'] ? 'selected' : '' ?>>
                                            <?= e($tipo['codigo']) ?> — <?= e($tipo['etiqueta']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="perfil-documento">Número de documento</label>
                                <input class="form-control" id="perfil-documento" name="numero_documento" maxlength="80" value="<?= e($perfil['numero_documento'] ?? '') ?>" required>
                                <div class="form-text">Vista protegida: <?= e($maskedDocument !== '' ? $maskedDocument : 'sin registrar') ?>. Se normaliza para detectar duplicados dentro de la firma.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="perfil-telefono">Teléfono</label>
                                <input class="form-control" id="perfil-telefono" name="telefono" maxlength="25" inputmode="tel" value="<?= e($perfil['telefono'] ?? '') ?>" placeholder="+57 300 000 0000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="perfil-foto">Fotografía</label>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($perfil['foto_perfil_url'])): ?>
                                        <img src="<?= e($perfil['foto_perfil_url']) ?>" alt="Fotografía de perfil" class="rounded-circle border" width="64" height="64" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle border bg-body-secondary d-flex align-items-center justify-content-center text-secondary" style="width:64px;height:64px;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <input class="form-control" id="perfil-foto" name="foto_perfil" type="file" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text">JPG, PNG o WebP. Máximo 5 MB. Se almacena fuera de la carpeta pública.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary mt-4" type="submit">
                            <i class="bi bi-check2-circle me-1"></i>Guardar información personal
                        </button>
                    </form>
                <?php else: ?>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombres</dt><dd class="col-sm-8"><?= e($perfil['nombres'] ?? $perfil['nombre']) ?></dd>
                        <dt class="col-sm-4">Apellidos</dt><dd class="col-sm-8"><?= e($perfil['apellidos'] ?? 'Sin registrar') ?></dd>
                        <dt class="col-sm-4">Documento</dt><dd class="col-sm-8"><?= e($maskedDocument !== '' ? $maskedDocument : 'Sin registrar') ?></dd>
                        <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8"><?= e($perfil['telefono'] ?? 'Sin registrar') ?></dd>
                    </dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0"><i class="bi bi-briefcase me-2"></i>Información profesional</h2>
            </div>
            <div class="card-body">
                <?php if ($canEdit): ?>
                    <form action="/mi-perfil/profesional" method="post" data-ajax-form>
                        <input type="hidden" name="_method" value="PATCH">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="perfil-es-abogado">Condición profesional</label>
                                <select class="form-select" id="perfil-es-abogado" name="es_abogado" required>
                                    <option value="0" <?= (int) ($perfil['es_abogado'] ?? 0) === 0 ? 'selected' : '' ?>>No abogado(a)</option>
                                    <option value="1" <?= (int) ($perfil['es_abogado'] ?? 0) === 1 ? 'selected' : '' ?>>Abogado(a)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="perfil-tiene-tarjeta">Tarjeta profesional</label>
                                <select class="form-select" id="perfil-tiene-tarjeta" name="tiene_tarjeta_profesional" required>
                                    <option value="0" <?= (int) ($perfil['tiene_tarjeta_profesional'] ?? 0) === 0 ? 'selected' : '' ?>>No registra tarjeta</option>
                                    <option value="1" <?= (int) ($perfil['tiene_tarjeta_profesional'] ?? 0) === 1 ? 'selected' : '' ?>>Sí registra tarjeta</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="perfil-tarjeta">Número de tarjeta profesional</label>
                                <input class="form-control" id="perfil-tarjeta" name="numero_tarjeta_profesional" maxlength="80" value="<?= e($perfil['numero_tarjeta_profesional'] ?? '') ?>">
                                <div class="form-text">Vista protegida: <?= e($maskedCard !== '' ? $maskedCard : 'sin registrar') ?>. Al modificarla queda pendiente de verificación administrativa.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Verificación</label>
                                <div><span class="badge <?= e($verificationClass) ?>"><?= e($verificationLabel) ?></span></div>
                                <?php if (!empty($perfil['observacion_verificacion_tarjeta'])): ?>
                                    <div class="small text-secondary mt-1"><?= e($perfil['observacion_verificacion_tarjeta']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button class="btn btn-primary mt-4" type="submit">
                            <i class="bi bi-check2-circle me-1"></i>Guardar información profesional
                        </button>
                    </form>
                <?php else: ?>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Cargo</dt>
                        <dd class="col-sm-7"><?= e($perfil['cargo'] ?? 'Sin asignar') ?> <span class="badge text-bg-light ms-1">Solo lectura</span></dd>
                        <dt class="col-sm-5">Condición profesional</dt>
                        <dd class="col-sm-7"><?= (int) ($perfil['es_abogado'] ?? 0) === 1 ? 'Abogado(a)' : 'No registrada como abogado(a)' ?></dd>
                        <dt class="col-sm-5">Tarjeta profesional</dt>
                        <dd class="col-sm-7"><?= e($maskedCard !== '' ? $maskedCard : 'Sin registrar') ?></dd>
                        <dt class="col-sm-5">Verificación</dt>
                        <dd class="col-sm-7"><span class="badge <?= e($verificationClass) ?>"><?= e($verificationLabel) ?></span></dd>
                    </dl>
                <?php endif; ?>
                <p class="small text-secondary mt-3 mb-0">La verificación de tarjeta solo puede hacerla un usuario autorizado distinto al titular.</p>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title mb-0"><i class="bi bi-shield-lock me-2"></i>Cuenta</h2>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Correo</dt><dd><?= e($perfil['email']) ?></dd>
                    <dt>Estado</dt><dd><span class="badge <?= $perfil['estado'] === 'activo' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e($perfil['estado']) ?></span></dd>
                    <dt>Tipo de usuario</dt><dd><?= e($perfil['tipo']) ?></dd>
                    <dt>Último acceso</dt><dd><?= e($perfil['last_login_at'] ?? 'Sin registro') ?></dd>
                </dl>
                <div class="alert alert-light border mt-3 mb-0 small">
                    El correo, estado, tipo, roles y permisos no se modifican desde Mi perfil.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0"><i class="bi bi-people me-2"></i>Roles asignados</h2>
            </div>
            <div class="card-body">
                <?php if ($roles === []): ?>
                    <span class="text-secondary">Sin roles asignados.</span>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($roles as $role): ?>
                            <span class="badge text-bg-primary"><?= e($role['nombre']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
