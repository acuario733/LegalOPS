<?php declare(strict_types=1); $estados = ['abierto', 'en_proceso', 'esperando_cliente', 'cerrado']; ?>
<div data-feedback hidden></div>
<div class="card" data-module="soporte-global">
    <div class="card-header"><h2 class="card-title">Cola global de soporte</h2></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Firma</th><th>Ticket</th><th>Prioridad</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= e($ticket['firma_nombre']) ?></td>
                    <td><strong><?= e($ticket['asunto']) ?></strong><div class="small text-secondary">#<?= (int) $ticket['id'] ?> · <?= e($ticket['creado_por_nombre'] ?? '') ?></div></td>
                    <td><?= e($ticket['prioridad']) ?></td>
                    <td><?= e($ticket['estado']) ?></td>
                    <td class="text-end"><form action="/superadmin/soporte/<?= (int) $ticket['firma_id'] ?>/<?= (int) $ticket['id'] ?>/estado" method="post" data-ajax-form class="d-inline-flex gap-2"><select class="form-select form-select-sm" name="estado"><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= $ticket['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary">Guardar</button></form></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($tickets === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay tickets globales.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
