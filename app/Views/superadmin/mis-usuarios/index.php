<?php

declare(strict_types=1);

$currentId   = (int) ($currentUser['id'] ?? 0);
$stats       = $stats       ?? ['activos' => 0, 'ultimo_login' => null];
$usuarios    = $usuarios    ?? [];
$roles       = $roles       ?? [];
$userRoleMap = $userRoleMap ?? [];

$rolesJson       = json_encode(array_values($roles),    JSON_UNESCAPED_UNICODE);
$userRoleMapJson = json_encode($userRoleMap,            JSON_UNESCAPED_UNICODE);

$initials = static function (string $nombre): string {
    $words = preg_split('/\s+/', trim($nombre)) ?: ['?'];
    $a     = mb_strtoupper(mb_substr($words[0], 0, 1));
    $b     = isset($words[1]) ? mb_strtoupper(mb_substr($words[1], 0, 1)) : '';
    return $a . $b ?: '??';
};
?>

<div data-feedback hidden></div>

<!-- ── HEADER ──────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title">Usuarios Superadmin</h1>
        <p class="page-subtitle">Gestiona los operadores internos de la plataforma.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearSuperadmin">
        <i class="bi bi-person-plus me-1"></i>Nuevo usuario
    </button>
</div>

<!-- ── MÉTRICAS ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--color-green-light);color:var(--color-green);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <p class="stat-label mb-0">Superadmins activos</p>
                    <div class="stat-value"><?= (int)$stats['activos'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:rgba(13,27,62,0.08);color:var(--color-navy);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                    <i class="bi bi-person-check"></i>
                </div>
                <div>
                    <p class="stat-label mb-0">Total en lista</p>
                    <div class="stat-value"><?= count($usuarios) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;border-radius:var(--radius-md);background:rgba(217,119,6,0.1);color:var(--color-warning);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <p class="stat-label mb-0">Último acceso del equipo</p>
                    <?php if ($stats['ultimo_login']): ?>
                    <div style="font-size:var(--font-size-sm);font-weight:600;"><?= e($stats['ultimo_login']['nombre']) ?></div>
                    <div style="font-size:var(--font-size-xs);color:var(--color-text-muted);"><?= e(substr((string)$stats['ultimo_login']['last_login_at'], 0, 16)) ?></div>
                    <?php else: ?>
                    <div style="font-size:var(--font-size-sm);color:var(--color-text-muted);">Sin registros</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── TABLA ────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:48px;"></th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Cargo</th>
                        <th>Roles</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th style="width:200px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u):
                        $isMe   = (int)$u['id'] === $currentId;
                        $active = $u['estado'] === 'activo';
                    ?>
                    <tr <?= $isMe ? 'style="background:rgba(13,27,62,0.03);"' : '' ?>>
                        <td>
                            <div style="width:36px;height:36px;border-radius:var(--radius-full);background:var(--color-navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">
                                <?= e($initials((string)$u['nombre'])) ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight:600;font-size:var(--font-size-sm);"><?= e($u['nombre']) ?></span>
                            <?php if ($isMe): ?>
                            <span class="badge-status badge-fijo ms-1" style="font-size:10px;">Tú</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:var(--font-size-sm);color:var(--color-text-secondary);"><?= e($u['email']) ?></td>
                        <td style="font-size:var(--font-size-sm);color:var(--color-text-secondary);"><?= e($u['cargo'] ?? '—') ?></td>
                        <td>
                            <?php
                            $uRoleIds = $userRoleMap[(int)$u['id']] ?? [];
                            if ($uRoleIds && $roles) {
                                foreach ($roles as $r) {
                                    if (in_array((int)$r['id'], $uRoleIds, true)) {
                                        echo '<span class="badge bg-secondary me-1" style="font-size:10px;">'
                                           . htmlspecialchars($r['nombre'])
                                           . '</span>';
                                    }
                                }
                            } else {
                                echo '<span class="text-muted" style="font-size:var(--font-size-xs);">—</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge-status <?= $active ? 'badge-confirmada' : 'badge-cancelada' ?>" data-estado-id="<?= (int)$u['id'] ?>">
                                <?= $active ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
                            <?= $u['last_login_at'] ? e(substr((string)$u['last_login_at'], 0, 16)) : 'Nunca' ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-ghost btn-sm btn-editar-sa"
                                        <?= $isMe ? 'disabled title="No puedes editarte a ti mismo"' : '' ?>
                                        data-bs-toggle="modal" data-bs-target="#modalEditarSuperadmin"
                                        data-id="<?= (int)$u['id'] ?>"
                                        data-nombre="<?= e($u['nombre']) ?>"
                                        data-email="<?= e($u['email']) ?>"
                                        data-cargo="<?= e($u['cargo'] ?? '') ?>"
                                        data-roles="<?= htmlspecialchars(json_encode($userRoleMap[(int)$u['id']] ?? [])) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-ghost btn-sm btn-reset-pass"
                                        <?= $isMe ? 'disabled' : '' ?>
                                        data-id="<?= (int)$u['id'] ?>"
                                        data-nombre="<?= e($u['nombre']) ?>"
                                        title="Resetear contraseña">
                                    <i class="bi bi-key"></i>
                                </button>
                                <?php if ($active): ?>
                                <button class="btn btn-ghost btn-sm btn-desactivar"
                                        <?= $isMe ? 'disabled' : '' ?>
                                        data-id="<?= (int)$u['id'] ?>"
                                        data-nombre="<?= e($u['nombre']) ?>"
                                        title="Desactivar" style="color:var(--color-danger);">
                                    <i class="bi bi-person-x"></i>
                                </button>
                                <?php else: ?>
                                <button class="btn btn-ghost btn-sm btn-reactivar"
                                        data-id="<?= (int)$u['id'] ?>"
                                        data-nombre="<?= e($u['nombre']) ?>"
                                        title="Reactivar" style="color:var(--color-green);">
                                    <i class="bi bi-person-check"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($usuarios === []): ?>
                    <tr><td colspan="8" class="text-center py-5" style="color:var(--color-text-muted);">No hay usuarios superadmin registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CREAR USUARIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearSuperadmin" tabindex="-1" aria-labelledby="labelCrearSa">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="labelCrearSa">Nuevo usuario superadmin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input class="form-control" id="crear-nombre" name="nombre" required placeholder="Ej: Ana García">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input class="form-control" id="crear-email" name="email" type="email" required placeholder="correo@empresa.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Cargo / Puesto</label>
                    <input class="form-control" id="crear-cargo" name="cargo" placeholder="Ej: Soporte Técnico">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input class="form-control" id="crear-password" name="password" type="password" required minlength="8" placeholder="Mínimo 8 caracteres">
                        <button class="btn btn-outline-secondary" type="button" id="toggle-crear-pass">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                    <input class="form-control" id="crear-password-confirm" name="password_confirm" type="password" required placeholder="Repite la contraseña">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-submit-crear">Crear usuario</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: EDITAR USUARIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarSuperadmin" tabindex="-1" aria-labelledby="labelEditarSa">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="labelEditarSa">Editar usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editar-id">
                <div class="mb-3">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input class="form-control" id="editar-nombre" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input class="form-control" id="editar-email" type="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cargo / Puesto</label>
                    <input class="form-control" id="editar-cargo">
                </div>
                <?php if (!empty($roles)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Roles asignados</label>
                    <div class="row g-2" id="editarRolesGrid">
                        <?php foreach ($roles as $r): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input editar-rol-check"
                                       type="checkbox"
                                       id="editar_rol_<?= (int)$r['id'] ?>"
                                       value="<?= (int)$r['id'] ?>">
                                <label class="form-check-label small" for="editar_rol_<?= (int)$r['id'] ?>">
                                    <?= htmlspecialchars($r['nombre']) ?>
                                    <?php if ((int)($r['is_protected'] ?? 0)): ?>
                                    <i class="bi bi-lock-fill text-warning" title="Protegido"></i>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="p-3" style="background:rgba(13,27,62,0.04);border-radius:var(--radius-sm);font-size:var(--font-size-xs);color:var(--color-text-muted);">
                    <i class="bi bi-info-circle me-1"></i>Para cambiar la contraseña usa la acción "Resetear contraseña".
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-submit-editar">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: NUEVA CONTRASEÑA (se muestra tras resetear)
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevaPassword" tabindex="-1" aria-labelledby="labelNuevoPass" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom-color:var(--color-warning);">
                <h5 class="modal-title" id="labelNuevoPass">
                    <i class="bi bi-key me-2" style="color:var(--color-warning);"></i>Contraseña temporal generada
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" style="font-size:var(--font-size-sm);color:var(--color-text-secondary);">
                    Comparte esta contraseña con el usuario de forma segura.
                    <strong>No se volverá a mostrar.</strong>
                </p>
                <div class="input-group">
                    <input class="form-control" id="nueva-password-display" type="text" readonly
                           style="font-family:monospace;font-size:1rem;letter-spacing:0.05em;">
                    <button class="btn btn-outline-secondary" type="button" id="btn-copiar-pass" title="Copiar">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <p class="mt-2 mb-0" style="font-size:var(--font-size-xs);color:var(--color-text-muted);">
                    El usuario deberá cambiarla en su próximo inicio de sesión.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const csrf        = <?= json_encode($csrfToken ?? '') ?>;
    const _roles      = <?= $rolesJson ?>;
    const _userRoleMap = <?= $userRoleMapJson ?>;

    /* ── helper: petición JSON ── */
    async function apiFetch(url, method, body = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        };
        if (body) opts.body = JSON.stringify(body);
        const res  = await fetch(url, opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Error en la solicitud.');
        return data;
    }

    function toast(msg, type = 'success') {
        if (window.LegalUI) LegalUI.toast(msg, type);
        else alert(msg);
    }

    /* ── Toggle contraseña ── */
    document.getElementById('toggle-crear-pass')?.addEventListener('click', function () {
        const input = document.getElementById('crear-password');
        const icon  = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });

    /* ── Copiar contraseña ── */
    document.getElementById('btn-copiar-pass')?.addEventListener('click', async function () {
        const val = document.getElementById('nueva-password-display').value;
        try {
            await navigator.clipboard.writeText(val);
            this.innerHTML = '<i class="bi bi-clipboard-check"></i>';
            setTimeout(() => { this.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        } catch {
            toast('No se pudo copiar automáticamente.', 'error');
        }
    });

    /* ── Pre-poblar modal editar ── */
    document.querySelectorAll('.btn-editar-sa').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('editar-id').value      = this.dataset.id;
            document.getElementById('editar-nombre').value  = this.dataset.nombre;
            document.getElementById('editar-email').value   = this.dataset.email;
            document.getElementById('editar-cargo').value   = this.dataset.cargo;

            // Pre-marcar roles
            const assignedRoles = JSON.parse(this.dataset.roles || '[]');
            document.querySelectorAll('.editar-rol-check').forEach(cb => {
                cb.checked = assignedRoles.includes(+cb.value);
            });
        });
    });

    /* ── CREAR usuario ── */
    document.getElementById('btn-submit-crear')?.addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        try {
            const res = await apiFetch('/superadmin/mis-usuarios', 'POST', {
                nombre:           document.getElementById('crear-nombre').value,
                email:            document.getElementById('crear-email').value,
                cargo:            document.getElementById('crear-cargo').value,
                password:         document.getElementById('crear-password').value,
                password_confirm: document.getElementById('crear-password-confirm').value,
            });
            toast(res.message || 'Usuario creado.');
            bootstrap.Modal.getInstance(document.getElementById('modalCrearSuperadmin'))?.hide();
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ── EDITAR usuario ── */
    document.getElementById('btn-submit-editar')?.addEventListener('click', async function () {
        const btn = this;
        const id  = document.getElementById('editar-id').value;
        btn.disabled = true;
        try {
            const rolesChecked = [...document.querySelectorAll('.editar-rol-check:checked')]
                                    .map(cb => +cb.value);
            const res = await apiFetch('/superadmin/mis-usuarios/' + id, 'PATCH', {
                nombre: document.getElementById('editar-nombre').value,
                email:  document.getElementById('editar-email').value,
                cargo:  document.getElementById('editar-cargo').value,
                roles:  rolesChecked,
            });
            toast(res.message || 'Usuario actualizado.');
            bootstrap.Modal.getInstance(document.getElementById('modalEditarSuperadmin'))?.hide();
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ── RESETEAR contraseña ── */
    document.querySelectorAll('.btn-reset-pass').forEach(btn => {
        btn.addEventListener('click', function () {
            const id     = this.dataset.id;
            const nombre = this.dataset.nombre;
            if (window.LegalUI) {
                LegalUI.confirm(
                    `¿Generar una contraseña temporal para <strong>${nombre}</strong>?<br>
                     <small>La contraseña actual quedará invalidada.</small>`,
                    async () => {
                        try {
                            const res = await apiFetch('/superadmin/mis-usuarios/' + id + '/reset-password', 'POST');
                            document.getElementById('nueva-password-display').value = res.data?.nueva_password ?? '';
                            new bootstrap.Modal(document.getElementById('modalNuevaPassword')).show();
                        } catch (err) {
                            toast(err.message, 'error');
                        }
                    },
                    { title: 'Resetear contraseña', confirmText: 'Generar contraseña', type: 'warning' }
                );
            }
        });
    });

    /* ── DESACTIVAR usuario ── */
    document.querySelectorAll('.btn-desactivar').forEach(btn => {
        btn.addEventListener('click', function () {
            const id     = this.dataset.id;
            const nombre = this.dataset.nombre;
            const row    = this.closest('tr');
            if (window.LegalUI) {
                LegalUI.confirm(
                    `¿Desactivar a <strong>${nombre}</strong>?`,
                    async () => {
                        try {
                            const res = await apiFetch('/superadmin/mis-usuarios/' + id + '/desactivar', 'POST');
                            toast(res.message || 'Usuario desactivado.');
                            const badge = row.querySelector('[data-estado-id]');
                            if (badge) { badge.textContent = 'Inactivo'; badge.className = 'badge-status badge-cancelada'; }
                            this.remove();
                        } catch (err) {
                            toast(err.message, 'error');
                        }
                    },
                    { title: 'Desactivar usuario', confirmText: 'Desactivar', type: 'danger' }
                );
            }
        });
    });

    /* ── REACTIVAR usuario ── */
    document.querySelectorAll('.btn-reactivar').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id   = this.dataset.id;
            const row  = this.closest('tr');
            try {
                const res = await apiFetch('/superadmin/mis-usuarios/' + id + '/reactivar', 'POST');
                toast(res.message || 'Usuario reactivado.');
                const badge = row.querySelector('[data-estado-id]');
                if (badge) { badge.textContent = 'Activo'; badge.className = 'badge-status badge-confirmada'; }
                this.remove();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    });
})();
</script>
