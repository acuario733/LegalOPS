<?php declare(strict_types=1); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Calendario</h1>
        <p class="page-subtitle">Audiencias, términos y citas del mes en un solo lugar</p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <!-- Leyenda -->
        <div class="d-flex gap-3 align-items-center" style="font-size:var(--font-size-xs);color:var(--color-text-secondary);">
            <span class="d-flex align-items-center gap-1">
                <span style="width:10px;height:10px;border-radius:50%;background:#DC2626;display:inline-block;"></span>
                Audiencias
            </span>
            <span class="d-flex align-items-center gap-1">
                <span style="width:10px;height:10px;border-radius:50%;background:#D97706;display:inline-block;"></span>
                Términos
            </span>
            <span class="d-flex align-items-center gap-1">
                <span style="width:10px;height:10px;border-radius:50%;background:#1A6B45;display:inline-block;"></span>
                Citas
            </span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-3">
        <div id="legalops-calendar"></div>
    </div>
</div>

<!-- FullCalendar v6 desde CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.9/locales/es.global.min.js"></script>

<script>
(function () {
    const el = document.getElementById('legalops-calendar');
    if (!el) return;

    let currentMes = new Date().toISOString().slice(0, 7); // YYYY-MM
    let cachedEvents = {};

    async function fetchEventos(mes) {
        if (cachedEvents[mes]) return cachedEvents[mes];
        try {
            const res  = await fetch('/api/calendario/eventos?mes=' + mes, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            cachedEvents[mes] = (data.eventos || []).map(ev => ({
                id:              ev.id,
                title:           ev.title,
                start:           ev.start,
                backgroundColor: ev.color,
                borderColor:     ev.color,
                extendedProps:   { tipo: ev.tipo, url: ev.url },
            }));
        } catch {
            cachedEvents[mes] = [];
        }
        return cachedEvents[mes];
    }

    const calendar = new FullCalendar.Calendar(el, {
        locale:             'es',
        initialView:        'dayGridMonth',
        height:             'auto',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listMonth',
        },
        buttonText: {
            today:     'Hoy',
            month:     'Mes',
            week:      'Semana',
            list:      'Lista',
        },
        events: async function (info, successCallback, failureCallback) {
            // FullCalendar llama esto al navegar; derivamos YYYY-MM del start del rango
            const mes = info.start.toISOString().slice(0, 7);
            // Puede que el rango cubra fin del mes anterior, pedimos el mes central
            const center = new Date(
                (new Date(info.start).getTime() + new Date(info.end).getTime()) / 2
            ).toISOString().slice(0, 7);
            const eventos = await fetchEventos(center);
            successCallback(eventos);
        },
        eventClick: function (info) {
            const url = info.event.extendedProps.url;
            if (url) window.location.href = url;
        },
        eventDidMount: function (info) {
            // Tooltip con tipo de evento
            info.el.title = info.event.extendedProps.tipo.charAt(0).toUpperCase()
                          + info.event.extendedProps.tipo.slice(1)
                          + ': ' + info.event.title;
        },
        // Estilos coherentes con el design system
        eventDisplay:     'block',
        dayMaxEvents:     3,
        moreLinkText:     n => `+${n} más`,
        noEventsText:     'Sin eventos este mes',
    });

    calendar.render();
}());
</script>

<style>
/* Override mínimo de FullCalendar para coincidir con design system */
#legalops-calendar {
    font-family: var(--font-base);
    font-size:   var(--font-size-sm);
}
.fc .fc-toolbar-title {
    font-size:   var(--font-size-lg);
    font-weight: 700;
    color:       var(--color-navy);
}
.fc .fc-button-primary {
    background:  var(--color-navy) !important;
    border-color:var(--color-navy) !important;
    font-size:   var(--font-size-xs) !important;
}
.fc .fc-button-primary:hover {
    background:  var(--color-navy-light) !important;
    border-color:var(--color-navy-light) !important;
}
.fc .fc-button-primary:not(:disabled).fc-button-active {
    background:  var(--color-green) !important;
    border-color:var(--color-green) !important;
}
.fc .fc-daygrid-day.fc-day-today { background: var(--color-green-light) !important; }
.fc .fc-event { cursor: pointer; border-radius: var(--radius-sm) !important; font-size: 11px !important; }
.fc .fc-col-header-cell { background: var(--color-bg); }
.fc .fc-scrollgrid { border-color: var(--color-border) !important; }
.fc td, .fc th { border-color: var(--color-border) !important; }
.fc .fc-more-link { color: var(--color-green); font-size: 11px; }
</style>
