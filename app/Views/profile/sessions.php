<?php declare(strict_types=1); ?>
<div data-feedback hidden></div><div class="card"><div class="card-header"><h2 class="card-title">Dispositivos y sesiones</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Dispositivo</th><th>IP</th><th>Última actividad</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($sessions as $item): ?><tr><td><?= e($item['user_agent'] ?: 'Desconocido') ?></td><td><?= e($item['ip_address']) ?></td><td><?= e($item['last_activity_at']) ?></td><td><?= $item['revoked_at'] ? 'Revocada' : 'Activa' ?></td><td><?php if (!$item['revoked_at']): ?><button class="btn btn-sm btn-outline-danger" data-action="/sesiones/<?= (int) $item['id'] ?>/revocar" data-confirm="¿Revocar esta sesión?">Revocar</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

