<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\PortalClienteController;

return static function (Router $router): void {
    $router->group('/portal', ['auth', 'firma', 'commercial', 'portal'], static function (Router $router): void {
        $router->get('', [PortalClienteController::class, 'index']);
        $router->get('/documentos/{id}/descargar', [PortalClienteController::class, 'download']);
        $router->post('/legal/{id}/aceptar', [PortalClienteController::class, 'accept']);
    });
};
