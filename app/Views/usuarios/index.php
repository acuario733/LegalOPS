<?php

declare(strict_types=1);

$canVerifyProfessional = (bool) ($canVerifyProfessional ?? false);
$currentUserId = (int) ($currentUser['id'] ?? 0);
?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Nuevo usuario</h2></div>
            <div class="card-body">
                <form action="/usuarios" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                    <div class="mb-2"><label class="form-label">Correo</label><input class="form-control" name="email" type="email" required></div>
                    <div class="mb-2"><label class="form-label">Contrasena inicial</label><input class="form-control" name="password" type="password" minlength="12" required></div>
                    <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="tipo"><option value="interno">Interno</option><option value="cliente_externo">Cliente externo</option></select></div>
                    <fieldset class="mb-3">
                        <legend class="form-label">Roles</legend>
                        <?php foreach ($roles as $rol): ?>
                            <label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int) $rol['id'] ?>"><span class="form-check-label"><?= e($rol['nombre']) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <button class="btn btn-primary">Crear usuario</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Equipo</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Usuario</th><th>Roles</th><th>Profesional</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <?php
                        $userRoles = array_map('intval', array_column((array) ($usuario['roles_detalle'] ?? []), 'id'));
                        $cardState = (string) ($usuario['tarjeta_profesional_verificacion_estado'] ?? '');
                        $cardLabel = match ($cardState) {
                            'pendiente' => 'Pendiente',
                            'verificada' => 'Verificada',
                            'rechazada' => 'Rechazada',
                            default => 'Sin tarjeta',
                        };
                        $cardClass = match ($cardState) {
                            'pendiente' => 'text-bg-warning',
                            'verificada' => 'text-bg-success',
                            'rechazada' => 'text-bg-danger',
                            default => 'text-bg-secondary',
                        };
                        ?>
                        <tr>
                            <td><strong><?= e($usuario['nombre']) ?></strong><div class="small text-secondary"><?= e($usuario['email']) ?></div></td>
                            <td><?= e($usuario['roles'] ?? 'Sin rol') ?></td>
                            <td>
                                <div><?= (int) ($usuario['es_abogado'] ?? 0) === 1 ? 'Abogado(a)' : 'No abogado(a)' ?></div>
                                <div class="small text-secondary">Tarjeta: <?= e($usuario['numero_tarjeta_profesional_enmascarado'] ?? 'Sin registrar') ?></div>
                                <span class="badge <?= e($cardClass) ?>"><?= e($cardLabel) ?></span>
                            </td>
                            <td><span class="badge <?= $usuario['estado'] === 'activo' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= e($usuario['estado']) ?></span></td>
                            <td>
                                <?php if ($usuario['estado'] === 'activo'): ?>
                                    <button class="btn btn-sm btn-outline-danger" data-action="/usuarios/<?= (int) $usuario['id'] ?>/desactivar" data-confirm="Desactivar usuario?">Desactivar</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success" data-action="/usuarios/<?= (int) $usuario['id'] ?>/reactivar">Reactivar</button>
                                <?php endif; ?>
                                <details class="mt-2">
                                    <summary class="small">Editar</summary>
                                    <form action="/usuarios/<?= (int) $usuario['id'] ?>" method="post" data-ajax-form class="mt-2">
                                        <input type="hidden" name="_method" value="PATCH">
                                        <input class="form-control form-control-sm mb-1" name="nombre" value="<?= e($usuario['nombre']) ?>">
                                        <input class="form-control form-control-sm mb-1" name="email" value="<?= e($usuario['email']) ?>">
                                        <select class="form-select form-select-sm mb-1" name="tipo">
                                            <option value="interno" <?= $usuario['tipo'] === 'interno' ? 'selected' : '' ?>>Interno</option>
                                            <option value="cliente_externo" <?= $usuario['tipo'] === 'cliente_externo' ? 'selected' : '' ?>>Cliente externo</option>
                                        </select>
                                        <select class="form-select form-select-sm mb-2" name="estado">
                                            <option value="activo" <?= $usuario['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                                            <option value="inactivo" <?= $usuario['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                                        </select>
                                        <div class="border rounded p-2 mb-2">
                                            <?php foreach ($roles as $rol): ?>
                                                <label class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int) $rol['id'] ?>" <?= in_array((int) $rol['id'], $userRoles, true) ? 'checked' : '' ?>><span class="form-check-label"><?= e($rol['nombre']) ?></span></label>
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="btn btn-sm btn-primary">Guardar</button>
                                    </form>
                                </details>
                                <?php if ($canVerifyProfessional && (int) ($usuario['tiene_tarjeta_profesional'] ?? 0) === 1 && (int) $usuario['id'] !== $currentUserId): ?>
                                    <details class="mt-2">
                                        <summary class="small">Verificar tarjeta</summary>
                                        <form action="/usuarios/<?= (int) $usuario['id'] ?>/tarjeta-profesional/verificar" method="post" data-ajax-form class="mt-2">
                                            <div class="small text-secondary mb-2">Tarjeta: <?= e($usuario['numero_tarjeta_profesional_enmascarado'] ?? 'Protegida') ?></div>
                                            <select class="form-select form-select-sm mb-1" name="estado" required>
                                                <option value="verificada">Aprobar</option>
                                                <option value="rechazada">Rechazar</option>
                                            </select>
                                            <textarea class="form-control form-control-sm mb-2" name="observacion" rows="2" maxlength="500" placeholder="Observación opcional"></textarea>
                                            <button class="btn btn-sm btn-outline-primary">Registrar verificación</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($usuarios === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay usuarios.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
