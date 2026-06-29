<?php
declare(strict_types=1);
$selectedCliente = (int) ($filters['cliente_id'] ?? 0);
?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="portal-autorizaciones">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Seleccionar cliente</h2></div>
            <div class="card-body">
                <form method="get" action="/portal-autorizaciones">
                    <label class="form-label">Cliente</label>
                    <select class="form-select mb-3" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Seleccione" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($clientes as $cliente): ?><?php if ($selectedCliente === (int) $cliente['id']): ?><option value="<?= (int) $cliente['id'] ?>" selected><?= e($cliente['nombre_razon_social']) ?></option><?php endif; ?><?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Consultar</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Autorizar recurso</h2></div>
            <div class="card-body">
                <form action="/portal-autorizaciones" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id" data-ajax-select data-url="/api/select/clientes" data-empty-label="Seleccione" required><option value="">Seleccione</option><?php foreach ($clientes as $cliente): ?><?php if ($selectedCliente === (int) $cliente['id']): ?><option value="<?= (int) $cliente['id'] ?>" selected><?= e($cliente['nombre_razon_social']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="recurso_tipo" data-resource-type><option value="caso">Caso</option><option value="documento">Documento</option><option value="honorario">Honorario</option><option value="pago">Pago</option><option value="gasto">Gasto</option><option value="usuario_cliente">Usuario portal</option></select></div>
                    <div class="mb-2"><label class="form-label">Recurso</label><select class="form-select" name="recurso_id" data-resource-id data-ajax-select data-url="/api/select/casos" data-parent-field="cliente_id" data-parent-param="cliente_id" data-empty-label="Seleccione" required><option value="">Seleccione</option></select></div>
                    <div class="mb-2"><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="autorizado">Autorizado</option><option value="revocado">Revocado</option></select></div>
                    <div class="mb-2"><label class="form-label">Observacion publica</label><textarea class="form-control" name="observacion_publica" rows="3" maxlength="2000"></textarea></div>
                    <div class="mb-3"><label class="form-label">Observacion interna</label><textarea class="form-control" name="observacion_interna" rows="3" maxlength="2000"></textarea></div>
                    <button class="btn btn-primary" type="submit">Guardar autorizacion</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Autorizaciones actuales</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Tipo</th><th>Recurso</th><th>Estado</th><th>Observacion</th></tr></thead>
                    <tbody>
                    <?php foreach ($autorizaciones as $item): ?>
                        <tr><td><?= e($item['tipo']) ?></td><td><?php if (($item['url'] ?? '') !== ''): ?><a href="<?= e($item['url']) ?>"><strong><?= e($item['nombre']) ?></strong></a><?php else: ?><strong><?= e($item['nombre']) ?></strong><?php endif; ?><div class="small text-secondary">#<?= (int) $item['recurso_id'] ?></div></td><td><span class="badge <?= $item['estado'] === 'autorizado' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($item['estado']) ?></span></td><td><?= e($item['observacion_publica'] ?? '') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($autorizaciones === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">Seleccione un cliente para ver autorizaciones.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
