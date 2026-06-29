<?php

declare(strict_types=1);
?>
<header class="mb-4">
    <div class="brand-wordmark fs-3 mb-4"><?= e($config['firma_nombre']) ?></div>
    <h1 class="display-5 fw-bold mb-2"><?= e($config['titulo']) ?></h1>
    <p class="lead text-muted-legal"><?= e($config['descripcion'] ?? 'Selecciona la fecha y hora que mejor se adapten a tu disponibilidad.') ?></p>
</header>
<div id="booking-calendar-app"
     data-slug="<?= e($config['slug']) ?>"
     data-days="<?= e(json_encode($config['dias_activos'])) ?>"
     data-min-days="<?= e($config['dias_anticipacion_min']) ?>"
     data-max-days="<?= e($config['dias_anticipacion_max']) ?>">
    <div class="row g-4">
        <div class="col-lg-6">
            <section class="public-panel p-4 mb-4">
                <h2 class="h4 mb-3">1. Selecciona una fecha</h2>
                <div class="d-flex justify-content-between align-items-center mb-3"><button class="btn btn-sm btn-outline-secondary" id="booking-prev-month" type="button" aria-label="Mes anterior">‹</button><strong id="booking-month-label"></strong><button class="btn btn-sm btn-outline-secondary" id="booking-next-month" type="button" aria-label="Mes siguiente">›</button></div>
                <div id="booking-calendar-grid" class="booking-calendar-grid"></div>
            </section>
            <section class="public-panel p-4" id="booking-slots-panel" hidden>
                <h2 class="h4 mb-3">2. Horarios disponibles</h2>
                <div class="d-flex flex-wrap gap-2" id="booking-slots"></div>
                <p class="text-muted-legal mb-0 mt-3" id="booking-no-slots" hidden>No hay horarios disponibles para esta fecha.</p>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="public-panel p-4" id="booking-data-panel" hidden>
                <h2 class="h4 mb-3">3. Tus datos</h2>
                <form id="booking-public-form">
                    <input type="hidden" name="fecha" id="booking-selected-date"><input type="hidden" name="hora_inicio" id="booking-selected-time">
                    <div class="mb-3"><label class="form-label" for="booking-client-name">Nombre completo *</label><input id="booking-client-name" name="nombre_cliente" class="form-control" maxlength="200" required></div>
                    <div class="mb-3"><label class="form-label" for="booking-client-email">Correo electrónico *</label><input id="booking-client-email" name="email_cliente" type="email" class="form-control" maxlength="200" required></div>
                    <div class="mb-3"><label class="form-label" for="booking-client-phone">Teléfono</label><input id="booking-client-phone" name="telefono_cliente" type="tel" class="form-control" maxlength="50"></div>
                    <div class="mb-3"><label class="form-label" for="booking-notes">Notas</label><textarea id="booking-notes" name="notas" class="form-control" rows="4" maxlength="2000"></textarea></div>
                    <button class="btn btn-legal btn-lg w-100" type="submit">Confirmar cita</button>
                </form>
                <div class="alert alert-danger mt-3" id="booking-error" hidden></div>
            </section>
            <section class="success-panel rounded-3 p-4" id="booking-confirmation" hidden>
                <h2 class="h4">Tu cita está confirmada</h2>
                <p id="booking-confirmation-summary"></p>
                <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-success" id="booking-google-link" target="_blank" rel="noopener">Agregar a Google Calendar</a><a class="btn btn-outline-success" id="booking-ics-link">Descargar .ics</a></div>
            </section>
        </div>
    </div>
</div>
<style>
.booking-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.4rem}.calendar-label{text-align:center;font-size:.78rem;color:#667085;padding:.35rem}.calendar-day{border:0;background:transparent;border-radius:8px;min-height:42px;color:#087b4f}.calendar-day:hover:not(:disabled),.calendar-day.is-selected{background:#087b4f;color:#fff}.calendar-day:disabled{color:#b2bac5}.slot-button.is-selected{background:#087b4f;color:#fff;border-color:#087b4f}
</style>
