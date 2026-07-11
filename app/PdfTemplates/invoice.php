<?php

declare(strict_types=1);

$invoice = is_array($data['invoice'] ?? null) ? $data['invoice'] : $data;
$lineas = is_array($invoice['lineas'] ?? null) ? $invoice['lineas'] : [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border-bottom: 1px solid #E5E7EB; padding: 8px; text-align: left; }
        th { background: #F3F4F6; }
        .total { text-align: right; font-size: 16px; font-weight: bold; margin-top: 18px; }
    </style>
</head>
<body>
    <?php if (!empty($invoice['logo_data_uri'])): ?>
        <img src="<?= htmlspecialchars((string) $invoice['logo_data_uri']) ?>" alt="" style="max-height:60px;max-width:180px">
    <?php endif; ?>
    <h1>Factura <?= htmlspecialchars((string) ($invoice['numero'] ?? '')) ?></h1>
    <p><strong>Firma:</strong> <?= htmlspecialchars((string) ($invoice['firma_nombre'] ?? '')) ?></p>
    <p><strong>Cliente:</strong> <?= htmlspecialchars((string) ($invoice['cliente_nombre'] ?? '')) ?></p>
    <p><strong>Concepto:</strong> <?= htmlspecialchars((string) ($invoice['concepto'] ?? '')) ?></p>
    <table>
        <thead>
            <tr>
                <th>Descripcion</th>
                <th>Cantidad</th>
                <th>Tarifa</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lineas as $linea): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($linea['descripcion'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($linea['cantidad'] ?? '1.00')) ?></td>
                <td><?= htmlspecialchars((string) ($linea['tarifa'] ?? '0.00')) ?></td>
                <td><?= htmlspecialchars((string) ($linea['monto'] ?? '0.00')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="total">Total: <?= htmlspecialchars((string) ($invoice['total'] ?? $invoice['monto'] ?? '0.00')) ?></p>
    <?php if (!empty($invoice['pago_url'])): ?>
        <p><strong>Pago en linea:</strong> <?= htmlspecialchars((string) $invoice['pago_url']) ?></p>
        <?php if (!empty($invoice['qr_data_uri'])): ?>
            <img src="<?= htmlspecialchars((string) $invoice['qr_data_uri']) ?>" alt="QR de pago" style="width:120px;height:120px">
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
