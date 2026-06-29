<?php

declare(strict_types=1);
?>
<div class="row justify-content-center"><div class="col-lg-7">
    <header class="mb-4"><div class="brand-wordmark fs-3 mb-4"><?= e($appointment['firma_nombre']) ?></div><h1 class="display-6 fw-bold">Cancelar cita</h1></header>
    <section class="public-panel p-4 p-md-5">
        <?php if ($appointment['estado'] === 'cancelada'): ?>
            <div class="success-panel rounded-3 p-4"><h2 class="h4">Esta cita ya fue cancelada</h2><p class="mb-0">No necesitas realizar ninguna acción adicional.</p></div>
        <?php elseif ($appointment['estado'] === 'confirmada'): ?>
            <h2 class="h4"><?= e($appointment['booking_titulo']) ?></h2>
            <dl class="row mt-4"><dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e($appointment['fecha']) ?></dd><dt class="col-sm-4">Hora</dt><dd class="col-sm-8"><?= e(substr((string) $appointment['hora_inicio'],0,5)) ?></dd><dt class="col-sm-4">Cliente</dt><dd class="col-sm-8"><?= e($appointment['nombre_cliente']) ?></dd></dl>
            <a class="btn btn-danger mt-3" href="?confirmar=1" onclick="return confirm('¿Confirmas la cancelación de la cita?')">Confirmar cancelación</a>
        <?php else: ?>
            <div class="alert alert-secondary mb-0">Esta cita ya no puede cancelarse.</div>
        <?php endif; ?>
    </section>
</div></div>
