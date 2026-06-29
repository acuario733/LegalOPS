<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\AceptacionLegalController;
use App\Controllers\AuditoriaController;
use App\Controllers\CatalogoController;
use App\Controllers\ChecklistOwnerController;
use App\Controllers\FirmaController;
use App\Controllers\PlanController;
use App\Controllers\SoporteController;
use App\Controllers\UsuarioController;

return static function (Router $router): void {
    $router->group('/superadmin', ['auth', 'superadmin'], static function (Router $router): void {
        $router->get('/firmas', [FirmaController::class, 'index'], ['permission:firmas.ver']);
        $router->post('/firmas', [FirmaController::class, 'store'], ['permission:firmas.crear']);
        $router->patch('/firmas/{id}', [FirmaController::class, 'update'], ['permission:firmas.editar']);
        $router->post('/firmas/{id}/facturacion', [FirmaController::class, 'billing'], ['permission:firmas.editar']);
        $router->post('/firmas/suspensiones-automaticas', [FirmaController::class, 'automaticSuspensions'], ['permission:firmas.suspender']);
        $router->post('/firmas/{id}/suspender', [FirmaController::class, 'suspend'], ['permission:firmas.suspender']);
        $router->post('/firmas/{id}/reactivar', [FirmaController::class, 'reactivate'], ['permission:firmas.reactivar']);
        $router->get('/firmas/{id}/uso', [FirmaController::class, 'usage'], ['permission:firmas.ver']);
        $router->post('/firmas/{firmaId}/administrador', [UsuarioController::class, 'storeForFirma'], ['permission:firmas.editar']);

        $router->get('/planes', [PlanController::class, 'index'], ['permission:planes.ver']);
        $router->post('/planes', [PlanController::class, 'store'], ['permission:planes.crear']);
        $router->patch('/planes/{id}', [PlanController::class, 'update'], ['permission:planes.editar']);
        $router->post('/planes/asignar', [PlanController::class, 'assign'], ['permission:planes.asignar']);
        $router->post('/limites/excepcion', [PlanController::class, 'overrideLimit'], ['permission:limites.editar']);

        $router->get('/auditoria', [AuditoriaController::class, 'index'], ['permission:auditoria.ver']);
        $router->get('/catalogos', [CatalogoController::class, 'index'], ['permission:configuracion.ver']);
        $router->post('/catalogos', [CatalogoController::class, 'store'], ['permission:configuracion.editar']);
        $router->get('/catalogos/{id}', [CatalogoController::class, 'detail'], ['permission:configuracion.ver']);
        $router->post('/catalogos/{id}/items', [CatalogoController::class, 'storeItem'], ['permission:configuracion.editar']);
        $router->patch('/catalogos/{catalogId}/items/{itemId}', [CatalogoController::class, 'updateItem'], ['permission:configuracion.editar']);
        $router->post('/catalogos/{catalogId}/items/{itemId}/estado', [CatalogoController::class, 'itemStatus'], ['permission:configuracion.editar']);
        $router->get('/legal', [AceptacionLegalController::class, 'admin'], ['permission:legal.ver']);
        $router->post('/legal', [AceptacionLegalController::class, 'store'], ['permission:legal.administrar']);
        $router->post('/legal/{id}/publicar', [AceptacionLegalController::class, 'publish'], ['permission:legal.administrar']);

        $router->get('/soporte', [SoporteController::class, 'global'], ['permission:soporte.ver_global']);
        $router->post('/soporte/{firmaId}/{id}/estado', [SoporteController::class, 'globalStatus'], ['permission:soporte.ver_global']);

        $router->get('/checklist', [ChecklistOwnerController::class, 'index'], ['permission:checklist.ver']);
        $router->post('/checklist', [ChecklistOwnerController::class, 'store'], ['permission:checklist.evaluar']);
        $router->get('/checklist/{id}', [ChecklistOwnerController::class, 'show'], ['permission:checklist.ver']);
        $router->post('/checklist/{id}/items/{itemId}', [ChecklistOwnerController::class, 'evaluate'], ['permission:checklist.evaluar']);
        $router->post('/checklist/{id}/decision', [ChecklistOwnerController::class, 'decide'], ['permission:checklist.aprobar']);
    });
};
