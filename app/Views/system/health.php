<?php

declare(strict_types=1);
?>
<section class="card health-hero shadow-sm mb-4">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex align-items-center gap-3 mb-3"><span class="health-dot" aria-hidden="true"></span><span class="text-uppercase small fw-semibold">Servicio disponible</span></div>
        <h2 class="display-6 fw-semibold mb-3">El núcleo de LegalOPS Cloud está operativo.</h2>
        <p class="lead mb-0 text-white-50">Router, middleware, sesión, CSRF, vistas y respuestas uniformes atraviesan el pipeline interno.</p>
    </div>
</section>

<div class="row g-3">
    <div class="col-md-4">
        <article class="card h-100 shadow-sm"><div class="card-body"><i class="bi bi-diagram-3 fs-3 text-primary"></i><h3 class="h5 mt-3">Pipeline</h3><p class="text-secondary mb-0">Front Controller → Router → Middleware → Controller → Response.</p></div></article>
    </div>
    <div class="col-md-4">
        <article class="card h-100 shadow-sm"><div class="card-body"><i class="bi bi-shield-check fs-3 text-success"></i><h3 class="h5 mt-3">Controles</h3><p class="text-secondary mb-0">Sesión segura, CSRF, errores saneados y permisos preparados para RBAC.</p></div></article>
    </div>
    <div class="col-md-4">
        <article class="card h-100 shadow-sm"><div class="card-body"><i class="bi bi-sliders fs-3 text-warning"></i><h3 class="h5 mt-3">Entorno</h3><p class="text-secondary mb-0">Configuración activa: <strong><?= e($environment) ?></strong>.</p></div></article>
    </div>
</div>

