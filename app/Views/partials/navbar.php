<?php

declare(strict_types=1);

$displayName = is_array($currentUser ?? null) ? (string) ($currentUser['name'] ?? 'Usuario') : 'Núcleo técnico';
?>
<nav class="app-header navbar navbar-expand bg-body shadow-sm">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item"><button class="nav-link btn btn-link sidebar-toggle-btn" data-lte-toggle="sidebar" type="button" aria-label="Alternar navegación"><i class="bi bi-list"></i></button></li>
            <li class="nav-item d-none d-md-block"><a href="/health" class="nav-link">Estado</a></li>
        </ul>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item"><span class="nav-link text-secondary"><?= e($contextLabel ?? $displayName) ?></span></li>
            <?php if (is_array($currentUser ?? null)): ?>
                <li class="nav-item"><a href="/mi-perfil" class="nav-link"><i class="bi bi-person-circle me-1"></i>Mi perfil</a></li>
                <li class="nav-item"><button type="button" class="nav-link btn btn-link" data-action="/logout" data-redirect="/login"><i class="bi bi-box-arrow-right me-1"></i>Salir</button></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
