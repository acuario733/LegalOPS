<?php declare(strict_types=1); $estados = ['abierto', 'en_proceso', 'esperando_cliente', 'cerrado']; ?>
<div data-feedback hidden></div>
<div class="row g-4" data-module="soporte">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?= e($ticket['asunto']) ?></h2></div>
            <div class="card-body">
                <dl class="row small text-secondary">
                    <dt class="col-sm-3">Estado</dt><dd class="col-sm-9"><?= e($ticket['estado']) ?></dd>
                    <dt class="col-sm-3">Prioridad</dt><dd class="col-sm-9"><?= e($ticket['prioridad']) ?></dd>
                    <dt class="col-sm-3">Categoria</dt><dd class="col-sm-9"><?= e($ticket['categoria'] ?? '') ?></dd>
                    <dt class="col-sm-3">SLA primera respuesta</dt><dd class="col-sm-9"><?= e($ticket['primera_respuesta_due_at'] ?? '') ?></dd>
                    <dt class="col-sm-3">SLA solucion</dt><dd class="col-sm-9"><?= e($ticket['solucion_due_at'] ?? '') ?></dd>
                </dl>
                <?php foreach ($ticket['mensajes'] as $mensaje): ?>
                    <div class="border-top py-3">
                        <div class="d-flex justify-content-between small text-secondary"><span><?= e($mensaje['autor_nombre'] ?? 'Sistema') ?> · <?= e($mensaje['visibilidad']) ?></span><span><?= e($mensaje['created_at']) ?></span></div>
                        <p class="mb-0 mt-2"><?= nl2br(e($mensaje['mensaje'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Responder</h2></div>
            <div class="card-body">
                <form action="/soporte/<?= (int) $ticket['id'] ?>/mensajes" method="post" data-ajax-form>
                    <input type="hidden" name="visibilidad" value="firma">
                    <div class="mb-3"><textarea class="form-control" name="mensaje" rows="5" required></textarea></div>
                    <button class="btn btn-primary" type="submit">Enviar mensaje</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Estado</h2></div>
            <div class="card-body">
                <form action="/soporte/<?= (int) $ticket['id'] ?>/estado" method="post" data-ajax-form>
                    <select class="form-select mb-3" name="estado"><?php foreach ($estados as $estado): ?><option value="<?= e($estado) ?>" <?= $ticket['estado'] === $estado ? 'selected' : '' ?>><?= e($estado) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-outline-primary" type="submit">Actualizar</button>
                </form>
            </div>
        </div>
    </div>
</div>
