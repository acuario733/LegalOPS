<?php declare(strict_types=1); ?>
<div class="card shadow-sm">
    <div class="card-body login-card-body">
        <p class="login-box-msg">Ingrese con sus credenciales</p>
        <div data-auth-feedback hidden></div>
        <form action="/login" method="post" data-auth-form>
            <div class="mb-3"><label class="form-label" for="email">Correo</label><input class="form-control" id="email" name="email" type="email" autocomplete="username" required></div>
            <div class="mb-3"><label class="form-label" for="firma">Firma <span class="text-secondary">(opcional para superadmin)</span></label><input class="form-control" id="firma" name="firma" autocomplete="organization" placeholder="slug-de-la-firma"></div>
            <div class="mb-3"><label class="form-label" for="password">Contraseña</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
            <button class="btn btn-primary w-100" type="submit">Ingresar</button>
        </form>
        <p class="mt-3 mb-0"><a href="/forgot-password">Olvidé mi contraseña</a></p>
    </div>
</div>

