<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Controllers\AudienciaController;
use App\Controllers\CasoController;
use App\Controllers\CasoParteController;
use App\Controllers\CasoTimelineController;
use App\Controllers\ClienteController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentoController;
use App\Controllers\FinanzasController;
use App\Controllers\ImportacionController;
use App\Controllers\NotificacionController;
use App\Controllers\PortalAutorizacionController;
use App\Controllers\ProspectoController;
use App\Controllers\SaldoController;
use App\Controllers\TareaController;
use App\Controllers\TerminoController;
use App\Core\Config;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/api/health', [HealthController::class, 'api']);
    $router->post('/api/health', [HealthController::class, 'post']);

    if (Config::get('app.environment') === 'development') {
        $router->get('/api/health/error', [HealthController::class, 'controlledError']);
    }

    $router->group('/api', ['auth', 'firma', 'commercial', 'internal'], static function (Router $router): void {
        $router->get('/dashboard', [DashboardController::class, 'api']);
        $router->get('/notificaciones', [NotificacionController::class, 'search'], ['permission:notificaciones.ver']);
        $router->get('/clientes', [ClienteController::class, 'search'], ['permission:clientes.ver']);
        $router->get('/clientes/{id}', [ClienteController::class, 'detail'], ['permission:clientes.ver']);
        $router->get('/prospectos', [ProspectoController::class, 'search'], ['permission:prospectos.ver']);
        $router->get('/casos', [CasoController::class, 'search'], ['permission:casos.ver']);
        $router->get('/casos/{id}', [CasoController::class, 'detail'], ['permission:casos.ver']);
        $router->get('/casos/{casoId}/partes', [CasoParteController::class, 'apiList'], ['permission:partes.ver']);
        $router->get('/casos/{casoId}/timeline', [CasoTimelineController::class, 'apiList'], ['permission:timeline.ver']);
        $router->get('/terminos', [TerminoController::class, 'search'], ['permission:terminos.ver']);
        $router->get('/terminos/{id}', [TerminoController::class, 'detail'], ['permission:terminos.ver']);
        $router->get('/audiencias', [AudienciaController::class, 'search'], ['permission:audiencias.ver']);
        $router->get('/audiencias/{id}', [AudienciaController::class, 'detail'], ['permission:audiencias.ver']);
        $router->get('/tareas', [TareaController::class, 'search'], ['permission:tareas.ver']);
        $router->get('/tareas/{id}', [TareaController::class, 'detail'], ['permission:tareas.ver']);
        $router->get('/documentos', [DocumentoController::class, 'search'], ['permission:documentos.ver']);
        $router->get('/documentos/{id}', [DocumentoController::class, 'detail'], ['permission:documentos.ver']);
        $router->get('/portal-autorizaciones', [PortalAutorizacionController::class, 'search'], ['permission:portal.autorizar']);
        $router->get('/importaciones', [ImportacionController::class, 'apiList'], ['permission:importaciones.ver']);
        $router->get('/finanzas/honorarios', [FinanzasController::class, 'searchHonorarios'], ['permission:finanzas.ver']);
        $router->get('/finanzas/honorarios/{id}', [FinanzasController::class, 'detailHonorario'], ['permission:finanzas.ver']);
        $router->get('/finanzas/pagos', [FinanzasController::class, 'searchPagos'], ['permission:finanzas.ver']);
        $router->get('/finanzas/pagos/{id}', [FinanzasController::class, 'detailPago'], ['permission:finanzas.ver']);
        $router->get('/finanzas/gastos', [FinanzasController::class, 'searchGastos'], ['permission:finanzas.ver']);
        $router->get('/finanzas/gastos/{id}', [FinanzasController::class, 'detailGasto'], ['permission:finanzas.ver']);
        $router->get('/finanzas/saldos', [SaldoController::class, 'search'], ['permission:finanzas.ver']);
    });
};
