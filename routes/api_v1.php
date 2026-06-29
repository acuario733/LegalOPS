<?php

declare(strict_types=1);

use App\Api\Controllers\V1\CasoApiController;
use App\Api\Controllers\V1\ClienteApiController;
use App\Api\Controllers\V1\TareaApiController;
use App\Core\Router;

/**
 * Rutas de la API pública v1.
 *
 * Autenticación: Bearer token (ApiAuthMiddleware).
 * Todas las rutas están bajo /api/v1/ y requieren el middleware 'api_auth'.
 *
 * Para registrar en App.php:
 *   $routes = require base_path('routes/api_v1.php');
 *   $routes($router);
 */
return static function (Router $router): void {
    $router->group('/api/v1', ['api_auth'], static function (Router $router): void {

        // ── Clientes ─────────────────────────────────────────────────────────
        $router->get('/clientes',     [ClienteApiController::class, 'index']);
        $router->get('/clientes/{id}', [ClienteApiController::class, 'show']);

        // ── Casos ─────────────────────────────────────────────────────────────
        $router->get('/casos',       [CasoApiController::class, 'index']);
        $router->get('/casos/{id}',  [CasoApiController::class, 'show']);

        // ── Tareas ────────────────────────────────────────────────────────────
        $router->get('/tareas',      [TareaApiController::class, 'index']);
        $router->get('/tareas/{id}', [TareaApiController::class, 'show']);
    });
};
