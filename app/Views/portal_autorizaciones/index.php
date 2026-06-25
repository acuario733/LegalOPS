<?php declare(strict_types=1); $selectedCliente = (int) ($filters['cliente_id'] ?? 0); ?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="portal-autorizaciones">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Seleccionar cliente</h2></div>
            <div class="card-body">
                <form method="get" action="/portal-autorizaciones">
                    <label class="form-label">Cliente</label>
                    <select class="form-select mb-3" name="cliente_id" required>
                        <option value="">Seleccione</option>
                        <?php foreach ($clientes as $cliente): ?><option value="<?= (int) $cliente['id'] ?>" <?= $selectedCliente === (int) $cliente['id'] ? 'selected' : '' ?>><?= e($cliente['nombre_razon_social']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Consultar</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Autorizar recurso</h2></div>
            <div class="card-body">
                <form action="/portal-autorizaciones" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Cliente</label><select class="form-select" name="cliente_id" required><option value="">Seleccione</option><?php foreach ($clientes as $cliente): ?><option value="<?= (int) $cliente['id'] ?>" <?= $selectedCliente === (int) $cliente['id'] ? 'selected' : '' ?>><?= e($cliente['nombre_razon_social']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" name="recurso_tipo" data-resource-type><option value="caso">Caso</option><option value="documento">Documento</option><option value="honorario">Honorario</option><option value="pago">Pago</option><option value="gasto">Gasto</option><option value="usuario_cliente">Usuario portal</option></select></div>
                    <div class="mb-2"><label class="form-label">Recurso</label><select class="form-select" name="recurso_id" data-resource-id required>
                        <?php foreach ($casos as $caso): ?><option data-type="caso" value="<?= (int) $caso['id'] ?>"><?= e($caso['titulo']) ?></option><?php endforeach; ?>
                        <?php foreach ($documentos as $documento): ?><option data-type="documento" value="<?= (int) $documento['id'] ?>"><?= e($documento['titulo']) ?></option><?php endforeach; ?>
                        <?php foreach ($honorarios as $honorario): ?><option data-type="honorario" value="<?= (int) $honorario['id'] ?>"><?= e($honorario['concepto']) ?></option><?php endforeach; ?>
                        <?php foreach ($pagos as $pago): ?><option data-type="pago" value="<?= (int) $pago['id'] ?>"><?= e(($pago['metodo_pago'] ?? 'Pago') . ' ' . ($pago['fecha_pago'] ?? '')) ?></option><?php endforeach; ?>
                        <?php foreach ($gastos as $gasto): ?><option data-type="gasto" value="<?= (int) $gasto['id'] ?>"><?= e($gasto['concepto']) ?></option><?php endforeach; ?>
                        <?php foreach ($usuariosExternos as $user): ?><option data-type="usuario_cliente" value="<?= (int) $user['id'] ?>"><?= e($user['nombre']) ?> - <?= e($user['email']) ?></option><?php endforeach; ?>
                    </select></div>
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
                        <tr><td><?= e($item['tipo']) ?></td><td><strong><?= e($item['nombre']) ?></strong><div class="small text-secondary">#<?= (int) $item['recurso_id'] ?></div></td><td><?= e($item['estado']) ?></td><td><?= e($item['observacion_publica'] ?? '') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ($autorizaciones === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">Seleccione un cliente para ver autorizaciones.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
