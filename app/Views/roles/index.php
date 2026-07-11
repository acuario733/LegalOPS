<?php
declare(strict_types=1);

$isSuperadmin = $isSuperadmin ?? false;

// Agrupar permisos por módulo para los checkboxes
$permisosPorModulo = [];
foreach ($permisos as $p) {
    $permisosPorModulo[$p['modulo']][] = $p;
}
ksort($permisosPorModulo);
?>

<div data-feedback hidden></div>

<div class="page-header">
    <div>
        <h1 class="page-title">Roles y permisos</h1>
        <p class="page-subtitle"><?= $isSuperadmin ? 'Vista global — todos los roles de todas las firmas' : 'Gestiona los roles de tu firma y sus permisos' ?></p>
    </div>
    <?php if (!$isSuperadmin): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-nuevo-rol">
        <i class="bi bi-plus-lg"></i>Nuevo rol
    </button>
    <?php endif; ?>
</div>

<!-- TABLA DE ROLES -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <?php if ($isSuperadmin): ?><th>Firma</th><?php endif; ?>
                        <th>Rol</th>
                        <th>Permisos</th>
                        <th>Usuarios</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?>
                    <tr>
                        <?php if ($isSuperadmin): ?>
                        <td style="font-size:var(--font-size-xs);color:var(--color-text-secondary);"><?= e($rol['firma_nombre'] ?? '') ?></td>
                        <?php endif; ?>
                        <td>
                            <div style="font-weight:600;font-size:var(--font-size-sm);"><?= e($rol['nombre']) ?></div>
                            <div><code style="font-size:11px;color:var(--color-text-muted);"><?= e($rol['codigo']) ?></code>
                            <?php if ((int)$rol['is_protected']): ?>
                                <span class="badge-status badge-fijo ms-1" title="No se puede eliminar ni cambiar permisos">Protegido</span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-size:var(--font-size-xs);color:var(--color-text-secondary);">
                            <?= count($rol['permisos_ids']) ?> permiso<?= count($rol['permisos_ids']) !== 1 ? 's' : '' ?>
                        </td>
                        <td><?= (int)$rol['usuarios'] ?></td>
                        <td>
                            <span class="badge-status <?= $rol['estado'] === 'activo' ? 'badge-confirmada' : 'badge-cancelada' ?>">
                                <?= ucfirst(e($rol['estado'])) ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal-editar-rol"
                                    data-rol='<?= e(json_encode([
                                        'id'           => $rol['id'],
                                        'firma_id'     => $rol['firma_id'] ?? null,
                                        'nombre'       => $rol['nombre'],
                                        'codigo'       => $rol['codigo'],
                                        'descripcion'  => $rol['descripcion'] ?? '',
                                        'estado'       => $rol['estado'],
                                        'is_protected' => (int)$rol['is_protected'],
                                        'permisos_ids' => $rol['permisos_ids'],
                                    ], JSON_UNESCAPED_UNICODE)) ?>'>
                                <i class="bi bi-pencil me-1"></i>Editar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($roles === []): ?>
                    <tr><td colspan="<?= $isSuperadmin ? 6 : 5 ?>" class="text-center py-5" style="color:var(--color-text-muted);">No hay roles configurados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: NUEVO ROL
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modal-nuevo-rol" tabindex="-1" aria-labelledby="label-nuevo-rol">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="label-nuevo-rol">Nuevo rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/roles" method="post" data-ajax-form>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Código <span class="text-danger">*</span></label>
                            <input class="form-control" name="codigo" required placeholder="ej: abogado_junior">
                            <div class="form-text">Solo letras, números y guión bajo.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input class="form-control" name="nombre" required placeholder="ej: Abogado Junior">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input class="form-control" name="descripcion" placeholder="Descripción del rol (opcional)">
                        </div>
                    </div>
                    <input type="hidden" name="is_protected" value="0">

                    <p style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-secondary);margin-bottom:var(--space-3);">
                        Permisos
                    </p>
                    <?php foreach ($permisosPorModulo as $modulo => $items): ?>
                    <div class="mb-3">
                        <p style="font-size:11px;font-weight:600;color:var(--color-navy);text-transform:capitalize;margin-bottom:var(--space-2);">
                            <?= e(str_replace('_', ' ', $modulo)) ?>
                        </p>
                        <div class="row g-1">
                            <?php foreach ($items as $p): ?>
                            <div class="col-sm-6 col-lg-4">
                                <label class="d-flex align-items-center gap-2 p-2" style="border:1px solid var(--color-border);border-radius:var(--radius-sm);cursor:pointer;font-size:12px;">
                                    <input class="form-check-input mb-0 flex-shrink-0" type="checkbox"
                                           name="permisos[]" value="<?= (int)$p['id'] ?>">
                                    <span><code style="font-size:10px;color:var(--color-text-muted);"><?= e($p['codigo']) ?></code></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear rol</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     MODAL: EDITAR ROL
══════════════════════════════════════════════════════ -->
<div class="modal fade" id="modal-editar-rol" tabindex="-1" aria-labelledby="label-editar-rol">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="label-editar-rol">Editar rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-editar-rol" method="post" data-ajax-form>
                <input type="hidden" name="_method" value="PATCH">
                <div class="modal-body">
                    <!-- Aviso para roles protegidos -->
                    <div id="edit-protected-notice" class="d-none mb-3 p-3"
                         style="background:var(--color-warning-light);border:1px solid var(--color-warning);border-radius:var(--radius-sm);font-size:var(--font-size-sm);">
                        <i class="bi bi-shield-lock me-2" style="color:var(--color-warning);"></i>
                        Este es un rol protegido del sistema. Solo puedes cambiar el nombre y la descripción; el código y los permisos no se pueden modificar.
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Código</label>
                            <input class="form-control" name="codigo" id="edit-codigo" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input class="form-control" name="nombre" id="edit-nombre" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" id="edit-estado">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input class="form-control" name="descripcion" id="edit-descripcion">
                        </div>
                    </div>

                    <div id="edit-permisos-section">
                        <p style="font-size:var(--font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-secondary);margin-bottom:var(--space-3);">
                            Permisos
                        </p>
                        <?php foreach ($permisosPorModulo as $modulo => $items): ?>
                        <div class="mb-3">
                            <p style="font-size:11px;font-weight:600;color:var(--color-navy);text-transform:capitalize;margin-bottom:var(--space-2);">
                                <?= e(str_replace('_', ' ', $modulo)) ?>
                            </p>
                            <div class="row g-1">
                                <?php foreach ($items as $p): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <label class="d-flex align-items-center gap-2 p-2 edit-perm-label"
                                           style="border:1px solid var(--color-border);border-radius:var(--radius-sm);cursor:pointer;font-size:12px;">
                                        <input class="form-check-input mb-0 flex-shrink-0 edit-perm-check"
                                               type="checkbox"
                                               name="permisos[]"
                                               value="<?= (int)$p['id'] ?>"
                                               data-perm-id="<?= (int)$p['id'] ?>">
                                        <span><code style="font-size:10px;color:var(--color-text-muted);"><?= e($p['codigo']) ?></code></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-guardar-rol">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const _isSuperadmin = <?= $isSuperadmin ? 'true' : 'false' ?>;

document.getElementById('modal-editar-rol')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    if (!btn) return;

    const rol  = JSON.parse(btn.getAttribute('data-rol') || '{}');
    const form = document.getElementById('form-editar-rol');

    // Superadmin usa el endpoint con override de protección; firma usa el normal
    if (_isSuperadmin) {
        form.action = '/superadmin/firmas/' + rol.firma_id + '/roles/' + rol.id + '/permisos';
    } else {
        form.action = '/roles/' + rol.id;
    }

    document.getElementById('edit-codigo').value      = rol.codigo      || '';
    document.getElementById('edit-nombre').value      = rol.nombre      || '';
    document.getElementById('edit-descripcion').value = rol.descripcion || '';
    document.getElementById('edit-estado').value      = rol.estado      || 'activo';

    const permIds = new Set((rol.permisos_ids || []).map(Number));
    document.querySelectorAll('.edit-perm-check').forEach(cb => {
        cb.checked = permIds.has(Number(cb.dataset.permId));
    });

    // Superadmin puede editar permisos en cualquier rol, incluso protegidos
    const isProtected = !_isSuperadmin && rol.is_protected === 1;
    document.getElementById('edit-protected-notice').classList.toggle('d-none', !(rol.is_protected === 1 && !_isSuperadmin));
    document.getElementById('edit-codigo').disabled = isProtected;
    document.getElementById('edit-estado').disabled = isProtected;
    document.querySelectorAll('.edit-perm-check').forEach(cb => {
        cb.disabled = isProtected;
        cb.closest('.edit-perm-label').style.opacity = isProtected ? '0.5' : '1';
        cb.closest('.edit-perm-label').style.cursor  = isProtected ? 'not-allowed' : 'pointer';
    });
});
</script>
