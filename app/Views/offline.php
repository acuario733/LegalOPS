<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a1a2e">
    <title>Sin conexión · LegalOPS Cloud</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f8f9fa;
            color: #212529;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .offline-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 3rem 2.5rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
        }

        .offline-logo {
            margin-bottom: 1.5rem;
        }

        .offline-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 0.75rem;
        }

        .offline-subtitle {
            font-size: 0.95rem;
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .offline-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            border: 2px solid transparent;
            min-height: 44px;
            transition: opacity 0.15s;
            text-decoration: none;
        }
        .btn:hover { opacity: 0.85; }

        .btn-primary {
            background: #1a1a2e;
            color: #fff;
        }

        .btn-outline {
            background: transparent;
            border-color: #1a1a2e;
            color: #1a1a2e;
        }

        @media (max-width: 400px) {
            .offline-card { padding: 2rem 1.25rem; }
            .btn-group { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-logo">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64"
                 role="img" aria-label="LegalOPS Cloud">
                <rect width="64" height="64" rx="14" fill="#172a46"/>
                <path d="M18 14h10v28h19v9H18z" fill="#d2a64f"/>
            </svg>
        </div>

        <span class="offline-icon" role="img" aria-label="Sin señal">📡</span>

        <h1 class="offline-title">Sin conexión</h1>
        <p class="offline-subtitle">
            No hay conexión a internet en este momento.<br>
            Las páginas que visitaste recientemente pueden estar disponibles.
        </p>

        <div class="btn-group">
            <button class="btn btn-primary" onclick="window.location.reload()">
                Reintentar
            </button>
            <a href="/dashboard" class="btn btn-outline">
                Ir al inicio
            </a>
        </div>
    </div>

    <script>
        // Auto-reintentar cuando se recupera la conexión
        window.addEventListener('online', () => window.location.reload());
    </script>
</body>
</html>
