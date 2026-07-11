<?php

declare(strict_types=1);

$firmaNombre = $config['firma_nombre'] ?? 'LegalOPS Cloud';
$firmaLetra  = strtoupper(mb_substr($firmaNombre, 0, 1));
?>

<!-- Encabezado de la firma -->
<div class="public-firm-header">
    <div class="firm-logo-circle"><?= e($firmaLetra) ?></div>
    <h1 class="public-firm-title"><?= e($config['titulo']) ?></h1>
    <p class="public-firm-subtitle">
        <?= e($config['descripcion'] ?? 'Selecciona la fecha y hora que mejor se adapten a tu disponibilidad.') ?>
    </p>
</div>

<!-- APP DE CALENDARIO -->
<div id="booking-calendar-app" class="public-two-col"
     data-slug="<?= e($config['slug']) ?>"
     data-days="<?= e(json_encode($config['dias_activos'])) ?>"
     data-min-days="<?= e($config['dias_anticipacion_min']) ?>"
     data-max-days="<?= e($config['dias_anticipacion_max']) ?>">

    <!-- COL IZQUIERDA: Calendario + slots -->
    <div>
        <!-- Card: Seleccionar fecha -->
        <div class="booking-card mb-3">
            <h2 class="booking-card-title">
                <span class="step-num">1</span>
                Selecciona una fecha
            </h2>
            <!-- Navegación del mes -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="slot-btn" id="booking-prev-month" type="button"
                        aria-label="Mes anterior" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;">
                    ‹
                </button>
                <strong id="booking-month-label" style="font-size:0.9rem;color:#0D1B3E;"></strong>
                <button class="slot-btn" id="booking-next-month" type="button"
                        aria-label="Mes siguiente" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;">
                    ›
                </button>
            </div>
            <div id="booking-calendar-grid" class="booking-calendar-grid"></div>
            <!-- Leyenda -->
            <div class="mt-3 d-flex gap-4" style="font-size:0.75rem;color:#94A3B8;">
                <span>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#1A6B45;margin-right:4px;"></span>
                    Disponible
                </span>
                <span>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#E2E8F0;margin-right:4px;"></span>
                    No disponible
                </span>
            </div>
        </div>

        <!-- Card: Horarios disponibles -->
        <div class="booking-card" id="booking-slots-panel" hidden>
            <h2 class="booking-card-title">
                <span class="step-num">2</span>
                Horarios disponibles
            </h2>
            <div class="slots-grid" id="booking-slots"></div>
            <p style="font-size:0.8rem;color:#94A3B8;" class="mb-0 mt-3" id="booking-no-slots" hidden>
                No hay horarios disponibles para esta fecha.
            </p>
            <p style="font-size:0.75rem;color:#94A3B8;margin-top:0.75rem;margin-bottom:0;">
                <i class="bi bi-globe2 me-1"></i>Hora local del sistema
            </p>
        </div>
    </div>

    <!-- COL DERECHA: Datos del cliente + confirmación -->
    <div>
        <!-- Card: Datos del cliente -->
        <div class="booking-card" id="booking-data-panel" hidden>
            <h2 class="booking-card-title">
                <span class="step-num">3</span>
                Tus datos
            </h2>
            <form id="booking-public-form">
                <input type="hidden" name="fecha"       id="booking-selected-date">
                <input type="hidden" name="hora_inicio" id="booking-selected-time">

                <div class="mb-3">
                    <label class="form-label" for="booking-client-name">Nombre completo *</label>
                    <input id="booking-client-name" name="nombre_cliente" class="form-control"
                           maxlength="200" required placeholder="Tu nombre completo">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="booking-client-email">Correo electrónico *</label>
                    <input id="booking-client-email" name="email_cliente" type="email" class="form-control"
                           maxlength="200" required placeholder="tu@email.com">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="booking-client-phone">Teléfono</label>
                    <input id="booking-client-phone" name="telefono_cliente" type="tel" class="form-control"
                           maxlength="50" placeholder="+57 300 000 0000">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="booking-notes">
                        Notas
                        <span style="font-weight:400;color:#94A3B8;">(opcional)</span>
                    </label>
                    <textarea id="booking-notes" name="notas" class="form-control" rows="4"
                              maxlength="500" placeholder="¿Hay algo específico que quieras tratar?"></textarea>
                    <div style="font-size:0.75rem;color:#94A3B8;text-align:right;margin-top:2px;">
                        <span id="booking-notes-count">0</span> / 500
                    </div>
                </div>

                <button class="btn btn-legal w-100" type="submit">
                    <i class="bi bi-calendar-check"></i>
                    Confirmar cita
                </button>
            </form>
            <div class="error-panel mt-3" id="booking-error" hidden></div>
        </div>

        <!-- Card: Confirmación exitosa -->
        <div class="booking-card" id="booking-confirmation" hidden>
            <div class="success-icon"><i class="bi bi-check-lg"></i></div>
            <p class="success-title mt-2">Tu cita está confirmada</p>
            <p class="success-subtitle">Hemos enviado los detalles a tu correo electrónico.</p>

            <ul style="list-style:none;padding:0;margin:1rem 0;font-size:0.875rem;color:#0D1B3E;" id="booking-confirmation-details">
            </ul>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn-legal" id="booking-google-link" target="_blank" rel="noopener"
                   style="text-decoration:none;border-radius:8px;padding:10px 16px;font-size:0.85rem;display:inline-flex;align-items:center;gap:6px;background:#fff;color:#1A6B45;border:1.5px solid #1A6B45;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google Calendar
                </a>
                <a class="btn-legal" id="booking-ics-link"
                   style="text-decoration:none;border-radius:8px;padding:10px 16px;font-size:0.85rem;display:inline-flex;align-items:center;gap:6px;background:#fff;color:#1A6B45;border:1.5px solid #1A6B45;">
                    <i class="bi bi-download"></i>
                    Descargar .ics
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Contador de caracteres en notas
document.getElementById('booking-notes')?.addEventListener('input', (e) => {
    const counter = document.getElementById('booking-notes-count');
    if (counter) counter.textContent = e.target.value.length;
});
</script>
