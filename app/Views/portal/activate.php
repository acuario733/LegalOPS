<!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activar portal</title>
<body><main>
<h1>Activar portal de <?= htmlspecialchars((string) $credential['firma_nombre']) ?></h1>
<p><?= htmlspecialchars((string) $credential['cliente_nombre']) ?></p>
<form method="post" action="/portal/activar/<?= rawurlencode((string) $token) ?>">
<input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
<label>Nueva contrasena <input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
<button type="submit">Activar acceso</button>
</form>
</main></body></html>
