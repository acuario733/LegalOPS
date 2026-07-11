<!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingreso al portal</title>
<body><main>
<h1>Portal de clientes</h1>
<form method="post" action="/portal/login">
<input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
<label>Email <input type="email" name="email" required autocomplete="username"></label>
<label>Contrasena <input type="password" name="password" required autocomplete="current-password"></label>
<button type="submit">Ingresar</button>
</form>
</main></body></html>
