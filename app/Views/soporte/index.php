<?php declare(strict_types=1); $prioridades = ['baja', 'media', 'alta', 'critica']; ?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="soporte">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Nuevo ticket</h2></div>
            <div class="card-body">
                <form action="/soporte" method="post" data-ajax-form>
                    <div class="mb-2"><label class="form-label">Asunto</label><input class="form-control" name="asunto" required></div>
                    <div class="mb-2"><label class="form-label">Categoria</label><input class="form-control" name="categoria"></div>
                    <div class="mb-2"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><?php foreach ($prioridades as $prioridad): ?><option value="<?= e($prioridad) ?>"><?= e($prioridad) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label">URL</label><input class="form-control" name="url" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>"></div>
                    <div class="mb-3"><label class="form-label">Mensaje</label><textarea class="form-control" name="mensaje" rows="4" required></textarea></div>
                    <button class="btn btn-primary" type="submit">Crear ticket</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Tickets</h2></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Ticket</th><th>Prioridad</th><th>Estado</th><th>SLA</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($tickets['items'] as $ticket): ?>
                        <tr>
                            <td><strong><?= e($ticket['asunto']) ?></strong><div class="small text-secondary"><?= e($ticket['categoria'] ?? '') ?></div></td>
                            <td><?= e($ticket['prioridad']) ?></td>
                            <td><?= e($ticket['estado']) ?></td>
                            <td class="small text-secondary">Respuesta: <?= e($ticket['primera_respuesta_due_at'] ?? '') ?><br>Solucion: <?= e($ticket['solucion_due_at'] ?? '') ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/soporte/<?= (int) $ticket['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($tickets['items'] === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">No hay tickets.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-secondary">Total: <?= (int) $tickets['total'] ?></div>
        </div>
    </div>
</div>
