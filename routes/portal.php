<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\BookingPublicController;
use App\Controllers\InboundEmailController;
use App\Controllers\IntakePublicController;
use App\Controllers\PortalClienteController;
use App\Controllers\PortalAuthController;

return static function (Router $router): void {
    $router->post('/webhooks/email-inbound', [InboundEmailController::class, 'receive'], ['rate_limit:public']);
    $router->get('/portal/activar/{token}', [PortalAuthController::class, 'showActivation']);
    $router->post('/portal/activar/{token}', [PortalAuthController::class, 'activate'], ['rate_limit:public']);
    $router->get('/portal/login', [PortalAuthController::class, 'showLogin']);
    $router->post('/portal/login', [PortalAuthController::class, 'login'], ['rate_limit:public']);

    $router->get('/intake/{firmaSlug}/{formSlug}', [IntakePublicController::class, 'show']);
    $router->post('/intake/{firmaSlug}/{formSlug}', [IntakePublicController::class, 'submit'], ['rate_limit:public']);

    $router->get('/booking/cancelar/{token}', [BookingPublicController::class, 'cancel']);
    $router->get('/booking/{slug}/ics/{token}', [BookingPublicController::class, 'ics']);
    $router->get('/booking/{slug}/slots', [BookingPublicController::class, 'slots'], ['rate_limit:public']);
    $router->get('/booking/{slug}', [BookingPublicController::class, 'show']);
    $router->post('/booking/{slug}', [BookingPublicController::class, 'store'], ['rate_limit:public']);

    $router->group('/portal', ['auth', 'firma', 'commercial', 'portal', 'legal_pending'], static function (Router $router): void {
        $router->get('', [PortalClienteController::class, 'index']);
        $router->get('/documentos/{id}/descargar', [PortalClienteController::class, 'download']);
        $router->post('/legal/{id}/aceptar', [PortalClienteController::class, 'accept']);
    });
};
