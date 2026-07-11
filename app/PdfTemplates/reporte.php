<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 22px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars((string) ($data['titulo'] ?? 'Reporte LegalOPS')) ?></h1>
    <div><?= nl2br(htmlspecialchars((string) ($data['contenido'] ?? ''))) ?></div>
</body>
</html>
