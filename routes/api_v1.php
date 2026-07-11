<?php

declare(strict_types=1);

use App\Api\Controllers\V1\CasoApiController;
use App\Api\Controllers\V1\ClienteApiController;
use App\Api\Controllers\V1\TareaApiController;
use App\Api\Controllers\V1\HonorarioApiController;
use App\Api\Controllers\V1\PagoApiController;
use App\Api\Controllers\V1\DocumentoApiController;
use App\Api\Controllers\V1\TimeEntryApiController;
use App\Api\Controllers\V1\WebhookApiController;
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

        $router->get('/honorarios', [HonorarioApiController::class, 'index']);
        $router->post('/honorarios', [HonorarioApiController::class, 'store']);
        $router->get('/pagos', [PagoApiController::class, 'index']);
        $router->post('/pagos', [PagoApiController::class, 'store']);
        $router->get('/documentos', [DocumentoApiController::class, 'index']);
        $router->post('/documentos', [DocumentoApiController::class, 'store']);
        $router->get('/documentos/{id}/descargar', [DocumentoApiController::class, 'download']);
        $router->get('/time-entries', [TimeEntryApiController::class, 'index']);
        $router->post('/time-entries', [TimeEntryApiController::class, 'store']);

        $router->get('/webhooks', [WebhookApiController::class, 'index']);
        $router->post('/webhooks', [WebhookApiController::class, 'store']);
        $router->patch('/webhooks/{id}', [WebhookApiController::class, 'update']);
        $router->delete('/webhooks/{id}', [WebhookApiController::class, 'destroy']);
        $router->get('/webhooks/{id}/entregas', [WebhookApiController::class, 'deliveries']);
    });
};
