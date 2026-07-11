<?php

declare(strict_types=1);

$roles    = $roles    ?? [];
$permisos = $permisos ?? [];
$usuarios = $usuarios ?? [];

// Agrupar permisos por módulo para el panel de edición
$permisosPorModulo = [];
foreach ($permisos as $p) {
    $permisosPorModulo[$p['modulo']][] = $p;
}
ksort($permisosPorModulo);

$rolesJson    = json_encode(array_values($roles),    JSON_UNESCAPED_UNICODE);
$permisosJson = json_encode(array_values($permisos), JSON_UNESCAPED_UNICODE);
$usuariosJson = json_encode(array_values($usuarios), JSON_UNESCAPED_UNICODE);
?>

<div data-feedback hidden></div>

<!-- ── HEADER ──────────────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title">Roles Superadmin</h1>
        <p class="page-subtitle">Define los perfiles de acceso para el equipo interno.</p>
    </div>
    <button class="btn btn-primary" id="btnNuevoRol">
        <i class="bi bi-shield-plus me-1"></i>Nuevo rol
    </button>
</div>

<!-- ── LAYOUT 2 PANELES ──────────────────────────────────────────── -->
<div class="row g-3" id="rolesLayout">

    <!-- Panel izquierdo: lista de tarjetas de roles -->
    <div class="col-lg-5" id="panelRoles">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Roles</span>
                <span class="badge bg-secondary" id="badgeTotalRoles"><?= count($roles) ?></span>
            </div>
            <div class="card-body p-0">
                <div id="listaRoles">
                    <?php if (empty($roles)): ?>
                    <div class="p-4 text-center text-muted small">No hay roles creados aún.</div>
                    <?php else: ?>
                    <?php foreach ($roles as $rol): ?>
                    <div class="rol-card border-bottom px-3 py-3 d-flex justify-content-between align-items-start
                                <?= (int)($rol['is_protected'] ?? 0) === 1 ? 'rol-protected' : '' ?>"
                         data-rol-id="<?= (int) $rol['id'] ?>"
                         role="button" tabindex="0">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold"><?= htmlspecialchars($rol['nombre']) ?></span>
                                <?php if ((int)($rol['is_protected'] ?? 0) === 1): ?>
                                <span class="badge bg-warning text-dark" title="Rol protegido del sistema">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <?php endif; ?>
                                <span class="badge <?= $rol['estado'] === 'activo' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= htmlspecialchars($rol['estado']) ?>
                                </span>
                            </div>
                            <small class="text-muted font-monospace"><?= htmlspecialchars($rol['codigo']) ?></small>
                            <?php if (!empty($rol['descripcion'])): ?>
                            <div class="small text-muted mt-1"><?= htmlspecialchars($rol['descripcion']) ?></div>
                            <?php endif; ?>
                            <div class="mt-1 small text-secondary">
                                <i class="bi bi-key me-1"></i><?= (int)($rol['total_permisos'] ?? 0) ?> permisos &nbsp;
                                <i class="bi bi-people me-1"></i><?= (int)($rol['total_usuarios'] ?? 0) ?> usuarios
                            </div>
                        </div>
                        <div class="d-flex gap-1 ms-2 flex-shrink-0">
                            <?php if ((int)($rol['is_protected'] ?? 0) !== 1): ?>
                            <button class="btn btn-sm btn-outline-secondary btn-edit-rol"
                                    data-rol-id="<?= (int) $rol['id'] ?>"
                                    data-nombre="<?= htmlspecialchars($rol['nombre']) ?>"
                                    data-codigo="<?= htmlspecialchars($rol['codigo']) ?>"
                                    data-descripcion="<?= htmlspecialchars($rol['descripcion'] ?? '') ?>"
                                    data-estado="<?= htmlspecialchars($rol['estado']) ?>"
                                    title="Editar rol">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: detalle del rol seleccionado -->
    <div class="col-lg-7" id="panelDetalle">
        <div class="card shadow-sm h-100" id="cardDetalle">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted py-5" id="detalleVacio">
                <i class="bi bi-shield-lock fs-1 mb-2 opacity-50"></i>
                <p class="mb-0">Selecciona un rol para ver sus permisos y usuarios asignados.</p>
            </div>

            <!-- Contenido del rol seleccionado (oculto al inicio) -->
            <div id="detalleContenido" style="display:none;">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <span class="fw-semibold" id="detalleNombre"></span>
                        <small class="font-monospace text-muted ms-2" id="detalleCodigo"></small>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" id="btnGuardarPermisos">
                            <i class="bi bi-floppy me-1"></i>Guardar permisos
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <!-- Tabs: Permisos / Usuarios -->
                    <ul class="nav nav-tabs nav-tabs-bordered px-3 pt-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPermisos" type="button">
                                <i class="bi bi-key me-1"></i>Permisos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUsuarios" type="button">
                                <i class="bi bi-people me-1"></i>Usuarios
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-3" style="max-height:520px; overflow-y:auto;">

                        <!-- TAB PERMISOS -->
                        <div class="tab-pane fade show active" id="tabPermisos" role="tabpanel">
                            <div id="gridPermisos">
                                <?php foreach ($permisosPorModulo as $modulo => $perms): ?>
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <label class="small fw-semibold text-uppercase text-muted"><?= htmlspecialchars($modulo) ?></label>
                                        <button type="button" class="btn btn-link btn-sm p-0 small text-primary btn-toggle-modulo"
                                                data-modulo="<?= htmlspecialchars($modulo) ?>">
                                            Todos
                                        </button>
                                    </div>
                                    <div class="row g-1">
                                        <?php foreach ($perms as $p): ?>
                                        <div class="col-6 col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input permiso-check"
                                                       type="checkbox"
                                                       id="perm_<?= (int) $p['id'] ?>"
                                                       value="<?= (int) $p['id'] ?>"
                                                       data-modulo="<?= htmlspecialchars($modulo) ?>">
                                                <label class="form-check-label small" for="perm_<?= (int) $p['id'] ?>">
                                                    <?= htmlspecialchars($p['accion']) ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- TAB USUARIOS -->
                        <div class="tab-pane fade" id="tabUsuarios" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Asignar usuario</label>
                                <div class="input-group">
                                    <select class="form-select form-select-sm" id="selectAsignarUsuario">
                                        <option value="">— Seleccionar usuario —</option>
                                        <?php foreach ($usuarios as $u): ?>
                                        <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?> &lt;<?= htmlspecialchars($u['email']) ?>&gt;</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" id="btnAsignarUsuario">
                                        <i class="bi bi-person-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="listaUsuariosRol">
                                <p class="text-muted small">Cargando...</p>
                            </div>
                        </div>

                    </div><!-- /tab-content -->
                </div>
            </div><!-- /detalleContenido -->
        </div>
    </div>

</div><!-- /row -->

<!-- ── MODAL CREAR / EDITAR ROL ──────────────────────────────────── -->
<div class="modal fade" id="modalRol" tabindex="-1" aria-labelledby="modalRolLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formRol" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRolLabel">Nuevo rol</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="formRolId" value="">

                    <div class="mb-3">
                        <label class="form-label" for="inputRolNombre">Nombre</label>
                        <input type="text" class="form-control" id="inputRolNombre" name="nombre"
                               placeholder="Ej: Soporte Técnico" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="inputRolCodigo">Código</label>
                        <input type="text" class="form-control font-monospace" id="inputRolCodigo" name="codigo"
                               placeholder="Ej: soporte_tecnico" required maxlength="60"
                               pattern="[a-z0-9_]+">
                        <div class="form-text">Solo minúsculas, números y guión bajo.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="inputRolDescripcion">Descripción</label>
                        <textarea class="form-control" id="inputRolDescripcion" name="descripcion"
                                  rows="2" maxlength="255" placeholder="Opcional"></textarea>
                    </div>
                    <div class="mb-3" id="wrapEstadoRol" style="display:none;">
                        <label class="form-label" for="selectRolEstado">Estado</label>
                        <select class="form-select" id="selectRolEstado" name="estado">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnFormRolSubmit">Crear rol</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── DATA & JS ──────────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    const CSRF       = <?= json_encode($csrfToken) ?>;
    const _roles     = <?= $rolesJson ?>;
    const _permisos  = <?= $permisosJson ?>;
    const _usuarios  = <?= $usuariosJson ?>;

    let _selectedRolId  = null;
    let _loadedPermIds  = [];
    let _loadedUserIds  = [];

    // ── Helpers ────────────────────────────────────────────────────
    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
    const feedback = LegalUI?.feedback ?? ((msg, t) => alert(msg));
    const toast    = LegalUI?.toast    ?? ((msg, t) => console.log(msg));

    const headers = () => ({
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF,
        'X-Requested-With': 'XMLHttpRequest',
    });

    const apiFetch = async (url, method, body = null) => {
        const opts = { method, headers: headers() };
        if (body) opts.body = JSON.stringify(body);
        const r = await fetch(url, opts);
        const json = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(json.message || `Error ${r.status}`);
        return json;
    };

    // ── Selección de rol ───────────────────────────────────────────
    function selectRol(rolId) {
        _selectedRolId = rolId;
        const rol = _roles.find(r => r.id == rolId);
        if (!rol) return;

        // Marcar card activa
        $$('.rol-card').forEach(c => c.classList.toggle('bg-light border-primary', +c.dataset.rolId === rolId));

        // Mostrar panel detalle
        $('#detalleVacio').style.display    = 'none';
        $('#detalleContenido').style.display = '';

        $('#detalleNombre').textContent = rol.nombre;
        $('#detalleCodigo').textContent = rol.codigo;

        loadPermissions(rolId);
        loadUsers(rolId);
    }

    // ── Cargar permisos del rol ─────────────────────────────────────
    async function loadPermissions(rolId) {
        try {
            const data = await apiFetch(`/superadmin/mis-roles/${rolId}/permisos`, 'GET');
            _loadedPermIds = Array.isArray(data.data) ? data.data : [];
            $$('.permiso-check').forEach(cb => {
                cb.checked = _loadedPermIds.includes(+cb.value);
            });
        } catch (e) {
            toast('Error cargando permisos: ' + e.message, 'danger');
        }
    }

    // ── Cargar usuarios del rol ─────────────────────────────────────
    async function loadUsers(rolId) {
        const container = $('#listaUsuariosRol');
        try {
            const data = await apiFetch(`/superadmin/mis-roles/${rolId}/usuarios`, 'GET');
            const users = Array.isArray(data.data) ? data.data : [];
            _loadedUserIds = users.map(u => +u.id);

            if (users.length === 0) {
                container.innerHTML = '<p class="text-muted small">Sin usuarios asignados.</p>';
                return;
            }
            container.innerHTML = users.map(u => `
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom usuario-row" data-uid="${u.id}">
                    <div>
                        <div class="fw-semibold small">${escHtml(u.nombre)}</div>
                        <div class="text-muted small">${escHtml(u.email)}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger btn-remover-usuario" data-uid="${u.id}" title="Remover">
                        <i class="bi bi-person-dash"></i>
                    </button>
                </div>`).join('');
        } catch (e) {
            container.innerHTML = '<p class="text-danger small">Error cargando usuarios.</p>';
        }
    }

    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, c =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // ── Guardar permisos ───────────────────────────────────────────
    $('#btnGuardarPermisos').addEventListener('click', async () => {
        if (!_selectedRolId) return;
        const permIds = $$('.permiso-check:checked').map(cb => +cb.value);
        try {
            await apiFetch(`/superadmin/mis-roles/${_selectedRolId}/permisos`, 'PATCH', { permisos: permIds });
            _loadedPermIds = permIds;
            toast('Permisos guardados correctamente.', 'success');
        } catch (e) {
            toast('Error: ' + e.message, 'danger');
        }
    });

    // ── Toggle módulo completo ──────────────────────────────────────
    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-toggle-modulo');
        if (!btn) return;
        const mod   = btn.dataset.modulo;
        const boxes = $$(`.permiso-check[data-modulo="${mod}"]`);
        const allOn = boxes.every(c => c.checked);
        boxes.forEach(c => { c.checked = !allOn; });
    });

    // ── Asignar usuario ────────────────────────────────────────────
    $('#btnAsignarUsuario').addEventListener('click', async () => {
        const sel = $('#selectAsignarUsuario');
        const uid = +sel.value;
        if (!uid || !_selectedRolId) return;
        if (_loadedUserIds.includes(uid)) {
            toast('Ese usuario ya tiene este rol asignado.', 'warning');
            return;
        }
        try {
            await apiFetch(`/superadmin/mis-roles/${_selectedRolId}/asignar`, 'POST', { usuario_id: uid });
            sel.value = '';
            await loadUsers(_selectedRolId);
            toast('Usuario asignado.', 'success');
        } catch (e) {
            toast('Error: ' + e.message, 'danger');
        }
    });

    // ── Remover usuario ────────────────────────────────────────────
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.btn-remover-usuario');
        if (!btn || !_selectedRolId) return;
        const uid = +btn.dataset.uid;
        const ok  = await LegalUI?.confirm?.('¿Remover este usuario del rol?') ?? true;
        if (!ok) return;
        try {
            await apiFetch(`/superadmin/mis-roles/${_selectedRolId}/remover`, 'POST', { usuario_id: uid });
            await loadUsers(_selectedRolId);
            toast('Usuario removido.', 'success');
        } catch (e) {
            toast('Error: ' + e.message, 'danger');
        }
    });

    // ── Click en tarjeta de rol ────────────────────────────────────
    document.addEventListener('click', e => {
        const card = e.target.closest('.rol-card');
        if (!card || e.target.closest('button')) return;
        selectRol(+card.dataset.rolId);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            const card = e.target.closest('.rol-card');
            if (!card) return;
            e.preventDefault();
            selectRol(+card.dataset.rolId);
        }
    });

    // ── Modal crear/editar rol ─────────────────────────────────────
    const modalRol    = new bootstrap.Modal('#modalRol');
    const formRol     = $('#formRol');
    const formRolId   = $('#formRolId');
    const inputNombre = $('#inputRolNombre');
    const inputCodigo = $('#inputRolCodigo');
    const inputDesc   = $('#inputRolDescripcion');
    const selEstado   = $('#selectRolEstado');
    const wrapEstado  = $('#wrapEstadoRol');
    const labelModal  = $('#modalRolLabel');
    const btnSubmit   = $('#btnFormRolSubmit');

    // Auto-generar código desde nombre (solo en creación)
    inputNombre.addEventListener('input', () => {
        if (formRolId.value) return;
        inputCodigo.value = inputNombre.value
            .toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9_\s]/g, '')
            .trim().replace(/\s+/g, '_');
    });

    $('#btnNuevoRol').addEventListener('click', () => {
        formRolId.value   = '';
        formRol.reset();
        labelModal.textContent   = 'Nuevo rol';
        btnSubmit.textContent    = 'Crear rol';
        wrapEstado.style.display = 'none';
        modalRol.show();
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-edit-rol');
        if (!btn) return;
        formRolId.value          = btn.dataset.rolId;
        inputNombre.value        = btn.dataset.nombre;
        inputCodigo.value        = btn.dataset.codigo;
        inputDesc.value          = btn.dataset.descripcion;
        selEstado.value          = btn.dataset.estado;
        labelModal.textContent   = 'Editar rol';
        btnSubmit.textContent    = 'Guardar cambios';
        wrapEstado.style.display = '';
        modalRol.show();
    });

    formRol.addEventListener('submit', async e => {
        e.preventDefault();
        if (!formRol.checkValidity()) { formRol.reportValidity(); return; }

        const isEdit = !!formRolId.value;
        const body   = {
            nombre:      inputNombre.value.trim(),
            codigo:      inputCodigo.value.trim(),
            descripcion: inputDesc.value.trim(),
            estado:      isEdit ? selEstado.value : 'activo',
        };

        try {
            btnSubmit.disabled = true;
            if (isEdit) {
                await apiFetch(`/superadmin/mis-roles/${formRolId.value}`, 'PATCH', body);
                toast('Rol actualizado.', 'success');
            } else {
                await apiFetch('/superadmin/mis-roles', 'POST', body);
                toast('Rol creado. Recargando...', 'success');
            }
            modalRol.hide();
            setTimeout(() => window.location.reload(), 800);
        } catch (e) {
            toast('Error: ' + e.message, 'danger');
        } finally {
            btnSubmit.disabled = false;
        }
    });

    // ── Auto-seleccionar primer rol si existe ──────────────────────
    if (_roles.length > 0) {
        selectRol(_roles[0].id);
    }

}());
</script>

<style>
.rol-card {
    cursor: pointer;
    transition: background-color .15s;
}
.rol-card:hover,
.rol-card:focus {
    background-color: var(--bs-light, #f8f9fa);
    outline: none;
}
.rol-card.bg-light {
    border-left: 3px solid var(--bs-primary) !important;
}
.rol-protected {
    background-color: rgba(255, 193, 7, .05);
}
</style>
