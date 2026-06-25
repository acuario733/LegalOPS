<?php declare(strict_types=1); ?>
<div class="card shadow-sm"><div class="card-body login-card-body">
    <p class="login-box-msg">Solicite instrucciones de recuperación</p>
    <div data-auth-feedback hidden></div>
    <form action="/forgot-password" method="post" data-auth-form>
        <div class="mb-3"><label class="form-label" for="email">Correo</label><input class="form-control" id="email" name="email" type="email" required></div>
        <div class="mb-3"><label class="form-label" for="firma">Firma (si aplica)</label><input class="form-control" id="firma" name="firma"></div>
        <button class="btn btn-primary w-100" type="submit">Solicitar recuperación</button>
    </form>
    <p class="mt-3 mb-0"><a href="/login">Volver al acceso</a></p>
</div></div>

