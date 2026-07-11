<?php

declare(strict_types=1);

$periodo = (string) ($data['periodo'] ?? '');
$libro = is_array($data['libro'] ?? null) ? $data['libro'] : ['items' => [], 'saldo_final' => '0.00'];
$items = is_array($libro['items'] ?? null) ? $libro['items'] : [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #E5E7EB; padding: 6px; text-align: left; }
        th { background: #F3F4F6; }
        .summary { margin-top: 16px; font-weight: bold; text-align: right; }
        .signature { margin-top: 48px; border-top: 1px solid #111827; width: 260px; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Conciliacion Trust</h1>
    <p>Periodo: <?= htmlspecialchars($periodo) ?></p>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Descripcion</th>
                <th>Monto</th>
                <th>Saldo acumulado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($item['fecha'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($item['cliente_nombre'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($item['tipo'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($item['descripcion'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($item['monto'] ?? '0.00')) ?></td>
                <td><?= htmlspecialchars((string) ($item['saldo_acumulado'] ?? '0.00')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="summary">Saldo final: <?= htmlspecialchars((string) ($libro['saldo_final'] ?? '0.00')) ?></p>
    <p class="signature">Firma del responsable</p>
</body>
</html>
