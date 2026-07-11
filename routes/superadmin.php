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
use App\Controllers\SuperadminRolController;
use App\Controllers\SuperadminUsuarioController;
use App\Controllers\SuperadminJobController;
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
        $router->get('/firmas/{id}', [FirmaController::class, 'show'], ['permission:firmas.ver']);
        $router->get('/firmas/{id}/uso', [FirmaController::class, 'usage'], ['permission:firmas.ver']);
        $router->post('/firmas/{firmaId}/administrador', [UsuarioController::class, 'storeForFirma'], ['permission:firmas.editar']);
        $router->patch('/firmas/{firmaId}/roles/{rolId}/permisos', [FirmaController::class, 'syncRolePermissions'], ['permission:firmas.editar']);

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

        // ── Usuarios Superadmin ───────────────────────────────────────────────
        $router->get('/mis-usuarios', [SuperadminUsuarioController::class, 'index'], ['permission:superadmin_usuarios.ver']);
        $router->post('/mis-usuarios', [SuperadminUsuarioController::class, 'store'], ['permission:superadmin_usuarios.crear']);
        $router->patch('/mis-usuarios/{id}', [SuperadminUsuarioController::class, 'update'], ['permission:superadmin_usuarios.editar']);
        $router->post('/mis-usuarios/{id}/desactivar', [SuperadminUsuarioController::class, 'deactivate'], ['permission:superadmin_usuarios.desactivar']);
        $router->post('/mis-usuarios/{id}/reactivar', [SuperadminUsuarioController::class, 'reactivate'], ['permission:superadmin_usuarios.reactivar']);
        $router->post('/mis-usuarios/{id}/reset-password', [SuperadminUsuarioController::class, 'resetPassword'], ['permission:superadmin_usuarios.resetear_password']);

        // ── Roles Superadmin ─────────────────────────────────────────────────
        $router->get('/mis-roles', [SuperadminRolController::class, 'index'], ['permission:superadmin_roles.ver']);
        $router->post('/mis-roles', [SuperadminRolController::class, 'store'], ['permission:superadmin_roles.crear']);
        $router->patch('/mis-roles/{id}', [SuperadminRolController::class, 'update'], ['permission:superadmin_roles.editar']);
        $router->patch('/mis-roles/{id}/permisos', [SuperadminRolController::class, 'syncPermissions'], ['permission:superadmin_roles.editar']);
        $router->post('/mis-roles/{id}/asignar', [SuperadminRolController::class, 'assign'], ['permission:superadmin_roles.asignar']);
        $router->post('/mis-roles/{id}/remover', [SuperadminRolController::class, 'unassign'], ['permission:superadmin_roles.asignar']);
        $router->get('/mis-roles/{id}/usuarios', [SuperadminRolController::class, 'roleUsers'], ['permission:superadmin_roles.ver']);
        $router->get('/mis-roles/{id}/permisos', [SuperadminRolController::class, 'rolePermissions'], ['permission:superadmin_roles.ver']);

        $router->get('/jobs', [SuperadminJobController::class, 'index']);
        $router->post('/jobs/failed/{id}/retry', [SuperadminJobController::class, 'retry']);

        $router->get('/checklist', [ChecklistOwnerController::class, 'index'], ['permission:checklist.ver']);
        $router->post('/checklist', [ChecklistOwnerController::class, 'store'], ['permission:checklist.evaluar']);
        $router->get('/checklist/{id}', [ChecklistOwnerController::class, 'show'], ['permission:checklist.ver']);
        $router->post('/checklist/{id}/items/{itemId}', [ChecklistOwnerController::class, 'evaluate'], ['permission:checklist.evaluar']);
        $router->post('/checklist/{id}/decision', [ChecklistOwnerController::class, 'decide'], ['permission:checklist.aprobar']);
    });
};
