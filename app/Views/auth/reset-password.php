<?php declare(strict_types=1); ?>
<div class="card shadow-sm"><div class="card-body login-card-body">
    <p class="login-box-msg">Defina una contraseña de al menos 12 caracteres</p>
    <div data-auth-feedback hidden></div>
    <form action="/reset-password/<?= e($token) ?>" method="post" data-auth-form>
        <div class="mb-3"><label class="form-label" for="password">Nueva contraseña</label><input class="form-control" id="password" name="password" type="password" minlength="12" required></div>
        <button class="btn btn-primary w-100" type="submit">Cambiar contraseña</button>
    </form>
</div></div>

