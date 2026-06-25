<?php declare(strict_types=1); ?>
<div data-feedback hidden></div>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><h2 class="card-title">Nueva firma</h2></div><div class="card-body">
            <form action="/superadmin/firmas" method="post" data-ajax-form>
                <div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                <div class="mb-3"><label class="form-label">Slug</label><input class="form-control" name="slug" required></div>
                <div class="mb-3"><label class="form-label">Zona horaria</label><input class="form-control" name="timezone" value="America/Bogota" required></div>
                <button class="btn btn-primary" type="submit">Crear firma</button>
            </form>
        </div></div>
        <div class="card"><div class="card-header"><h2 class="card-title">Asignar plan</h2></div><div class="card-body">
            <form action="/superadmin/planes/asignar" method="post" data-ajax-form>
                <div class="mb-3"><label class="form-label">Firma</label><select class="form-select" name="firma_id" required><?php foreach ($firmas as $firma): ?><option value="<?= (int) $firma['id'] ?>"><?= e($firma['nombre']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Plan</label><select class="form-select" name="plan_id" required><?php foreach ($planes as $plan): ?><option value="<?= (int) $plan['id'] ?>"><?= e($plan['nombre']) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-outline-primary" type="submit">Asignar</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8"><div class="card"><div class="card-header"><h2 class="card-title">Firmas registradas</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Firma</th><th>Estado</th><th>Plan</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($firmas as $firma): ?><tr>
            <td><strong><?= e($firma['nombre']) ?></strong><div class="small text-secondary"><?= e($firma['slug']) ?></div></td>
            <td><span class="badge text-bg-<?= $firma['estado'] === 'activa' ? 'success' : 'warning' ?>"><?= e($firma['estado']) ?></span></td>
            <td><?= e($firma['plan_nombre'] ?? 'Sin plan') ?></td>
            <td>
                <?php if ($firma['estado'] === 'activa'): ?><button class="btn btn-sm btn-outline-warning" data-action="/superadmin/firmas/<?= (int) $firma['id'] ?>/suspender" data-prompt="Motivo de suspensión" data-confirm="¿Suspender esta firma?">Suspender</button>
                <?php else: ?><button class="btn btn-sm btn-outline-success" data-action="/superadmin/firmas/<?= (int) $firma['id'] ?>/reactivar">Reactivar</button><?php endif; ?>
                <details class="mt-2"><summary class="small">Editar</summary><form class="mt-2" action="/superadmin/firmas/<?= (int) $firma['id'] ?>" method="post" data-ajax-form><input type="hidden" name="_method" value="PATCH"><input class="form-control form-control-sm mb-1" name="nombre" value="<?= e($firma['nombre']) ?>"><input class="form-control form-control-sm mb-1" name="slug" value="<?= e($firma['slug']) ?>"><input class="form-control form-control-sm mb-1" name="timezone" value="<?= e($firma['timezone']) ?>"><button class="btn btn-sm btn-primary">Guardar</button></form></details>
                <details class="mt-2"><summary class="small">Crear administrador inicial</summary><form class="mt-2" action="/superadmin/firmas/<?= (int) $firma['id'] ?>/administrador" method="post" data-ajax-form><input class="form-control form-control-sm mb-1" name="nombre" placeholder="Nombre" required><input class="form-control form-control-sm mb-1" name="email" type="email" placeholder="Correo" required><input class="form-control form-control-sm mb-1" name="password" type="password" minlength="12" placeholder="Contraseña inicial" required><input type="hidden" name="tipo" value="interno"><button class="btn btn-sm btn-primary">Crear administrador</button></form></details>
            </td>
        </tr><?php endforeach; ?>
        <?php if ($firmas === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">No hay firmas registradas.</td></tr><?php endif; ?>
        </tbody></table></div></div></div>
</div>
