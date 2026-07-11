<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pago de factura</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body{font-family:system-ui,sans-serif;margin:0;background:#f5f7fa;color:#17202a}
        main{max-width:640px;margin:48px auto;padding:28px;background:#fff;border:1px solid #dfe4ea;border-radius:8px}
        dl{display:grid;grid-template-columns:1fr 2fr;gap:10px;margin:24px 0}dt{font-weight:700}
        button{margin-top:18px;padding:11px 18px;border:0;border-radius:6px;background:#126b52;color:#fff;font-weight:700}
        #message{margin-top:14px;color:#a42828}
    </style>
</head>
<body>
<main>
    <h1><?= htmlspecialchars((string) $invoice['firma_nombre']) ?></h1>
    <dl>
        <dt>Factura</dt><dd><?= htmlspecialchars((string) ($invoice['numero'] ?: $invoice['id'])) ?></dd>
        <dt>Cliente</dt><dd><?= htmlspecialchars((string) $invoice['cliente_nombre']) ?></dd>
        <dt>Concepto</dt><dd><?= htmlspecialchars((string) $invoice['concepto']) ?></dd>
        <dt>Total</dt><dd><?= htmlspecialchars((string) $invoice['moneda']) ?> <?= number_format((float) $invoice['monto'], 2) ?></dd>
    </dl>
    <form id="payment-form">
        <div id="payment-element"></div>
        <button id="submit" type="submit">Pagar ahora</button>
        <p id="message" role="alert"></p>
    </form>
</main>
<script>
(async function () {
    const stripe = Stripe(<?= json_encode($stripeKey) ?>);
    const response = await fetch(<?= json_encode('/pay/' . $token . '/intent') ?>, {method:'POST',headers:{'Accept':'application/json'}});
    const payload = await response.json();
    if (!payload.ok) { document.getElementById('message').textContent = payload.message; return; }
    const elements = stripe.elements({clientSecret: payload.data.client_secret});
    elements.create('payment').mount('#payment-element');
    document.getElementById('payment-form').addEventListener('submit', async function (event) {
        event.preventDefault();
        const result = await stripe.confirmPayment({elements,confirmParams:{return_url:location.href}});
        if (result.error) document.getElementById('message').textContent = result.error.message;
    });
}());
</script>
</body>
</html>
