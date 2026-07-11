<?php

declare(strict_types=1);

$pageTitle = isset($title)     ? (string) $title     : 'LegalOPS Cloud';
$csrf      = isset($csrfToken) ? (string) $csrfToken  : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($pageTitle) ?> · LegalOPS Cloud</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --pf-navy:         #0D1B3E;
            --pf-navy-light:   #1A2B52;
            --pf-green:        #1A6B45;
            --pf-green-hover:  #155938;
            --pf-green-light:  #E8F5EE;
            --pf-muted:        #4A5568;
            --pf-border:       #E2E8F0;
            --pf-bg:           #0B1630;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height:  100vh;
            margin:      0;
            -webkit-font-smoothing: antialiased;
        }

        /* ── FONDO NAVY CON DECORACIÓN ── */
        .public-shell {
            min-height:  100vh;
            background:  linear-gradient(135deg, var(--pf-bg) 0%, #0D2040 60%, #122044 100%);
            position:    relative;
            overflow:    hidden;
            display:     flex;
            align-items: center;
            justify-content: center;
            padding:     2rem 1rem;
        }

        /* Decoración arquitectónica: columnas de luz */
        .public-shell::before {
            content:    '';
            position:   fixed;
            top:        0; left: 0; right: 0; bottom: 0;
            background:
                linear-gradient(160deg, rgba(26,107,69,0.08) 0%, transparent 40%),
                radial-gradient(ellipse at 80% 20%, rgba(26,107,69,0.12) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        /* Líneas verticales decorativas */
        .public-shell::after {
            content:    '';
            position:   fixed;
            top:        0; left: 0; right: 0; bottom: 0;
            background-image:
                repeating-linear-gradient(90deg,
                    rgba(255,255,255,0.015) 0px, rgba(255,255,255,0.015) 1px,
                    transparent 1px, transparent 80px);
            pointer-events: none;
            z-index: 0;
        }

        /* ── CARD CENTRAL ── */
        .public-card {
            background:    #fff;
            border-radius: 16px;
            box-shadow:    0 20px 60px rgba(0,0,0,0.25);
            padding:       48px;
            width:         100%;
            max-width:     600px;
            position:      relative;
            z-index:       1;
        }

        /* Logo de firma */
        .firm-logo-circle {
            width:          64px;
            height:         64px;
            border-radius:  50%;
            background:     var(--pf-green);
            color:          #fff;
            display:        flex;
            align-items:    center;
            justify-content:center;
            font-size:      24px;
            font-weight:    700;
            margin:         0 auto var(--space-4, 16px);
            flex-shrink:    0;
        }
        .firm-name {
            font-size:   1.25rem;
            font-weight: 700;
            color:       var(--pf-navy);
            text-align:  center;
            margin-bottom: 0.25rem;
        }

        /* Título y subtítulo del formulario */
        .public-title {
            font-size:   1.6rem;
            font-weight: 700;
            color:       var(--pf-navy);
            text-align:  center;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        .public-subtitle {
            font-size:   0.9rem;
            color:       var(--pf-muted);
            text-align:  center;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        /* Campos */
        .form-label {
            font-size:   0.8rem;
            font-weight: 600;
            color:       var(--pf-muted);
            margin-bottom: 4px;
        }
        .form-control,
        .form-select {
            border:        1.5px solid var(--pf-border);
            border-radius: 8px;
            font-size:     0.9rem;
            min-height:    46px;
            font-family:   inherit;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--pf-green);
            box-shadow:   0 0 0 3px rgba(26,107,69,0.12);
            outline:      none;
        }

        /* Botón principal */
        .btn-legal {
            background:    var(--pf-green);
            border-color:  var(--pf-green);
            color:         #fff;
            font-weight:   600;
            font-size:     1rem;
            min-height:    52px;
            border-radius: 8px;
            transition:    background 0.15s;
            display:       flex;
            align-items:   center;
            justify-content: center;
            gap:           8px;
            font-family:   inherit;
        }
        .btn-legal:hover,
        .btn-legal:focus {
            background:   var(--pf-green-hover);
            border-color: var(--pf-green-hover);
            color:        #fff;
        }
        .btn-legal:disabled {
            opacity: 0.65;
            cursor:  not-allowed;
        }

        /* Panel de éxito */
        .success-panel {
            border:        1px solid #9fd9c0;
            background:    var(--pf-green-light);
            border-radius: 12px;
            padding:       2rem;
            text-align:    center;
        }
        .success-icon {
            width:          56px;
            height:         56px;
            border-radius:  50%;
            background:     var(--pf-green);
            color:          #fff;
            display:        flex;
            align-items:    center;
            justify-content:center;
            font-size:      24px;
            margin:         0 auto 1rem;
        }
        .success-title {
            font-size:   1.1rem;
            font-weight: 700;
            color:       var(--pf-green);
        }
        .success-subtitle { color: var(--pf-muted); font-size: 0.9rem; }

        /* Alerta de error */
        .error-panel {
            background:    #FEE2E2;
            border:        1px solid #DC2626;
            border-radius: 8px;
            padding:       1rem 1.25rem;
            color:         #DC2626;
            font-size:     0.875rem;
        }

        /* Privacy link */
        .privacy-link { color: var(--pf-green); }

        /* Mobile */
        @media (max-width: 639.98px) {
            .public-card    { padding: 2rem 1.25rem; border-radius: 12px; }
            .public-title   { font-size: 1.3rem; }
            .firm-logo-circle{ width: 52px; height: 52px; font-size: 20px; }
        }

        /* Modo booking (2 columnas) */
        .public-two-col {
            display:  grid;
            grid-template-columns: 1fr 1fr;
            gap:      1.5rem;
            max-width:960px;
            width:    100%;
            position: relative;
            z-index:  1;
        }
        @media (max-width: 767.98px) {
            .public-two-col { grid-template-columns: 1fr; }
        }

        /* Cards dentro del booking */
        .booking-card {
            background:    #fff;
            border-radius: 12px;
            box-shadow:    0 4px 20px rgba(0,0,0,0.15);
            padding:       1.5rem;
        }
        .booking-card-title {
            font-size:     0.875rem;
            font-weight:   600;
            color:         var(--pf-navy);
            margin-bottom: 1rem;
            display:       flex;
            align-items:   center;
            gap:           8px;
        }
        .booking-card-title .step-num {
            width:          24px;
            height:         24px;
            border-radius:  50%;
            background:     var(--pf-navy);
            color:          #fff;
            font-size:      12px;
            display:        flex;
            align-items:    center;
            justify-content:center;
            flex-shrink:    0;
        }

        /* Firma header (booking público) */
        .public-firm-header {
            text-align:    center;
            margin-bottom: 1.5rem;
            position:      relative;
            z-index:       1;
        }
        .public-firm-header .firm-logo-circle { margin-bottom: 0.5rem; }
        .public-firm-title {
            font-size:   2rem;
            font-weight: 700;
            color:       #fff;
            margin-bottom: 0.5rem;
        }
        .public-firm-subtitle { color: rgba(255,255,255,0.65); font-size: 0.9rem; }

        /* Slot buttons */
        .slot-btn {
            border:        1.5px solid var(--pf-border);
            border-radius: 8px;
            background:    #fff;
            color:         var(--pf-navy);
            font-size:     0.85rem;
            font-weight:   500;
            padding:       8px 12px;
            cursor:        pointer;
            transition:    border-color 0.15s, background 0.15s;
            min-height:    40px;
            font-family:   inherit;
        }
        .slot-btn:hover        { border-color: var(--pf-green); background: var(--pf-green-light); }
        .slot-btn.is-selected  { background: var(--pf-green); border-color: var(--pf-green); color: #fff; }

        /* Calendario */
        .booking-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .booking-day-header    { font-size: 11px; font-weight: 600; color: var(--pf-muted); text-align: center; padding: 4px 0; }
        .booking-day {
            aspect-ratio:   1;
            border-radius:  50%;
            border:         none;
            background:     transparent;
            font-size:      0.8rem;
            cursor:         pointer;
            display:        flex;
            align-items:    center;
            justify-content:center;
            transition:     background 0.15s;
            font-family:    inherit;
        }
        .booking-day:not(:disabled):hover { background: var(--pf-green-light); color: var(--pf-green); }
        .booking-day.is-selected           { background: var(--pf-green); color: #fff; }
        .booking-day:disabled              { color: #CBD5E1; cursor: default; }
        .booking-day.other-month           { color: #CBD5E1; }

        /* Publicidad de slots — grid 4 columnas */
        .slots-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        @media (max-width:479.98px) { .slots-grid { grid-template-columns: repeat(3, 1fr); } }
    </style>
</head>
<body>
<main class="public-shell">
    <?= $content ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php if (!empty($publicScript)): ?>
    <script src="<?= e((string) $publicScript) ?>"></script>
<?php endif; ?>
</body>
</html>
