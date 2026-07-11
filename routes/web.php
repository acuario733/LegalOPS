<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Controllers\OfflineController;
use App\Controllers\AceptacionLegalController;
use App\Controllers\AudienciaController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CasoController;
use App\Controllers\CasoParteController;
use App\Controllers\CasoTimelineController;
use App\Controllers\CatalogoController;
use App\Controllers\ClienteController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentoController;
use App\Controllers\FinanzasController;
use App\Controllers\ImportacionController;
use App\Controllers\IntakeFormController;
use App\Controllers\CasoComunicacionController;
use App\Controllers\DocumentTemplateController;
use App\Controllers\NotificacionController;
use App\Controllers\OnboardingController;
use App\Controllers\PerfilController;
use App\Controllers\PortalAutorizacionController;
use App\Controllers\ProspectoController;
use App\Controllers\ReporteController;
use App\Controllers\RolController;
use App\Controllers\SaldoController;
use App\Controllers\SessionController;
use App\Controllers\SoporteController;
use App\Controllers\TareaController;
use App\Controllers\TerminoController;
use App\Controllers\TrustController;
use App\Controllers\PaymentController;
use App\Controllers\GoogleCalendarController;
use App\Controllers\DocuSignController;
use App\Controllers\UsuarioController;
use App\Controllers\BookingController;
use App\Controllers\CalendarioController;
use App\Core\Config;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', static fn () => App\Core\Response::redirect('/health'));
    $router->get('/health', [HealthController::class, 'html']);
    $router->get('/offline', [OfflineController::class, 'index']);
    $router->get('/pay/{token}', [PaymentController::class, 'show']);
    $router->post('/pay/{token}/intent', [PaymentController::class, 'intent'], ['rate_limit']);
    $router->post('/webhooks/stripe', [PaymentController::class, 'webhook']);
    $router->post('/webhooks/docusign', [DocuSignController::class, 'webhook']);

    $router->group('', ['guest'], static function (Router $router): void {
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login'], ['rate_limit']);
        $router->get('/forgot-password', [AuthController::class, 'showForgot']);
        $router->post('/forgot-password', [AuthController::class, 'forgot'], ['rate_limit']);
        $router->get('/reset-password/{token}', [AuthController::class, 'showReset']);
        $router->post('/reset-password/{token}', [AuthController::class, 'reset'], ['rate_limit']);
    });

    $router->group('', ['auth'], static function (Router $router): void {
        $router->post('/logout', [AuthController::class, 'logout']);
        $router->get('/mi-perfil', [PerfilController::class, 'show'], ['permission:perfil.ver']);
        $router->get('/mi-perfil/foto', [PerfilController::class, 'photo'], ['permission:perfil.ver']);
        $router->patch('/mi-perfil', [PerfilController::class, 'update'], ['permission:perfil.editar']);
        $router->patch('/mi-perfil/profesional', [PerfilController::class, 'updateProfessional'], ['permission:perfil.editar']);
        $router->patch('/mi-perfil/contrasena', [PerfilController::class, 'changePassword'], ['permission:perfil.editar']);
        $router->get('/sesiones', [SessionController::class, 'index'], ['permission:sesiones.ver']);
        $router->post('/sesiones/{id}/revocar', [SessionController::class, 'revoke'], ['permission:sesiones.revocar']);
        $router->get('/legal/pendientes', [AceptacionLegalController::class, 'pending'], ['permission:legal.ver']);
        $router->post('/legal/{id}/aceptar', [AceptacionLegalController::class, 'accept'], ['permission:legal.aceptar']);
    });

    $router->group('', ['auth', 'firma', 'commercial', 'internal', 'legal_pending'], static function (Router $router): void {
        $router->get('/dashboard', [DashboardController::class, 'index']);
        $router->get('/notificaciones', [NotificacionController::class, 'index'], ['permission:notificaciones.ver']);
        $router->post('/notificaciones/{id}/leer', [NotificacionController::class, 'read'], ['permission:notificaciones.marcar_leida']);

        $router->get('/usuarios', [UsuarioController::class, 'index'], ['permission:usuarios.ver']);
        $router->post('/usuarios', [UsuarioController::class, 'store'], ['permission:usuarios.crear', 'plan:usuarios']);
        $router->patch('/usuarios/{id}', [UsuarioController::class, 'update'], ['permission:usuarios.editar']);
        $router->post('/usuarios/{id}/desactivar', [UsuarioController::class, 'deactivate'], ['permission:usuarios.desactivar']);
        $router->post('/usuarios/{id}/reactivar', [UsuarioController::class, 'reactivate'], ['permission:usuarios.desactivar']);
        $router->post('/usuarios/{id}/tarjeta-profesional/verificar', [UsuarioController::class, 'verifyProfessionalCard'], ['permission:usuarios.verificar_profesional']);

        $router->get('/roles', [RolController::class, 'index'], ['permission:roles.ver']);
        $router->post('/roles', [RolController::class, 'store'], ['permission:roles.crear', 'plan:roles']);
        $router->patch('/roles/{id}', [RolController::class, 'update'], ['permission:roles.editar']);
        $router->post('/usuarios/{userId}/roles', [RolController::class, 'assign'], ['permission:roles.asignar']);

        $router->get('/auditoria', [AuditoriaController::class, 'index'], ['permission:auditoria.ver']);
        $router->get('/auditoria/exportar', [AuditoriaController::class, 'export'], ['permission:auditoria.exportar']);
        $router->get('/catalogos', [CatalogoController::class, 'index'], ['permission:configuracion.ver']);
        $router->post('/catalogos', [CatalogoController::class, 'store'], ['permission:configuracion.editar', 'plan:catalogos']);
        $router->get('/catalogos/{id}', [CatalogoController::class, 'detail'], ['permission:configuracion.ver']);
        $router->post('/catalogos/{id}/items', [CatalogoController::class, 'storeItem'], ['permission:configuracion.editar']);
        $router->patch('/catalogos/{catalogId}/items/{itemId}', [CatalogoController::class, 'updateItem'], ['permission:configuracion.editar']);
        $router->post('/catalogos/{catalogId}/items/{itemId}/estado', [CatalogoController::class, 'itemStatus'], ['permission:configuracion.editar']);

        $router->get('/clientes', [ClienteController::class, 'index'], ['permission:clientes.ver']);
        $router->get('/clientes/{id}', [ClienteController::class, 'show'], ['permission:clientes.ver']);
        $router->post('/clientes', [ClienteController::class, 'store'], ['permission:clientes.crear', 'plan:clientes']);
        $router->patch('/clientes/{id}', [ClienteController::class, 'update'], ['permission:clientes.editar']);
        $router->post('/clientes/{id}/eliminar', [ClienteController::class, 'delete'], ['permission:clientes.eliminar']);
        $router->post('/clientes/{id}/revelar', [ClienteController::class, 'reveal'], ['permission:clientes.revelar', 'reveal_limit']);
        $router->post('/clientes/{id}/olvidar', [ClienteController::class, 'olvidar'], ['permission:clientes.eliminar']);
        $router->get('/clientes/{id}/exportar-datos', [ClienteController::class, 'exportarDatos'], ['permission:clientes.eliminar']);

        $router->get('/prospectos', [ProspectoController::class, 'index'], ['permission:prospectos.ver']);
        $router->get('/prospectos/{id}', [ProspectoController::class, 'show'], ['permission:prospectos.ver']);
        $router->post('/prospectos', [ProspectoController::class, 'store'], ['permission:prospectos.crear', 'plan:prospectos']);
        $router->patch('/prospectos/{id}', [ProspectoController::class, 'update'], ['permission:prospectos.editar']);
        $router->post('/prospectos/{id}/estado', [ProspectoController::class, 'status'], ['permission:prospectos.editar']);
        $router->post('/prospectos/{id}/convertir', [ProspectoController::class, 'convert'], ['permission:prospectos.convertir']);

        $router->get('/intake', [IntakeFormController::class, 'index'], ['permission:intake.ver']);
        $router->get('/intake/nuevo', [IntakeFormController::class, 'builder'], ['permission:intake.crear']);
        $router->get('/intake/{id}/editar', [IntakeFormController::class, 'builder'], ['permission:intake.editar']);
        $router->post('/intake', [IntakeFormController::class, 'store'], ['permission:intake.crear']);
        $router->patch('/intake/{id}', [IntakeFormController::class, 'update'], ['permission:intake.editar']);
        $router->delete('/intake/{id}', [IntakeFormController::class, 'destroy'], ['permission:intake.eliminar']);
        $router->get('/intake/{id}/submissions', [IntakeFormController::class, 'submissions'], ['permission:intake.ver']);
        $router->get('/intake/{id}/preview', [IntakeFormController::class, 'preview'], ['permission:intake.ver']);

        $router->get('/booking', [BookingController::class, 'index'], ['permission:booking.ver']);
        $router->post('/booking', [BookingController::class, 'store'], ['permission:booking.crear']);
        $router->patch('/booking/{id}', [BookingController::class, 'update'], ['permission:booking.editar']);
        $router->delete('/booking/{id}', [BookingController::class, 'destroy'], ['permission:booking.eliminar']);
        $router->get('/booking/{id}/appointments', [BookingController::class, 'appointments'], ['permission:booking.ver']);
        $router->patch('/booking/appointments/{id}/complete', [BookingController::class, 'complete'], ['permission:booking.editar']);
        $router->patch('/booking/appointments/{id}/cancel', [BookingController::class, 'cancel'], ['permission:booking.editar']);

        // ── Calendario ─────────────────────────────────────────────────────
        $router->get('/calendario', [CalendarioController::class, 'index'], ['permission:calendario.ver']);
        $router->get('/api/calendario/eventos', [CalendarioController::class, 'eventos'], ['permission:calendario.ver']);
        $router->post('/calendario/eventos', [CalendarioController::class, 'store'], ['permission:calendario.ver']);
        $router->patch('/calendario/eventos/{id}', [CalendarioController::class, 'update'], ['permission:calendario.ver']);
        $router->delete('/calendario/eventos/{id}', [CalendarioController::class, 'destroy'], ['permission:calendario.ver']);
        $router->get('/integraciones/google-calendar/conectar', [GoogleCalendarController::class, 'connect'], ['permission:integraciones.google_calendar']);
        $router->get('/integraciones/google-calendar/callback', [GoogleCalendarController::class, 'callback'], ['permission:integraciones.google_calendar']);
        $router->post('/integraciones/google-calendar/sync', [GoogleCalendarController::class, 'sync'], ['permission:integraciones.google_calendar']);
        $router->delete('/integraciones/google-calendar/desconectar', [GoogleCalendarController::class, 'disconnect'], ['permission:integraciones.google_calendar']);

        $router->get('/casos', [CasoController::class, 'index'], ['permission:casos.ver']);
        $router->get('/casos/{id}', [CasoController::class, 'show'], ['permission:casos.ver']);
        $router->post('/casos', [CasoController::class, 'store'], ['permission:casos.crear', 'plan:casos']);
        $router->patch('/casos/{id}', [CasoController::class, 'update'], ['permission:casos.editar']);
        $router->patch('/casos/{id}/stage', [CasoController::class, 'stage'], ['permission:casos.editar']);
        $router->post('/casos/{id}/cerrar', [CasoController::class, 'close'], ['permission:casos.cerrar']);
        $router->post('/casos/{id}/archivar', [CasoController::class, 'archive'], ['permission:casos.archivar']);
        $router->post('/casos/{id}/reabrir', [CasoController::class, 'reopen'], ['permission:casos.editar']);
        $router->get('/casos/{casoId}/comunicaciones', [CasoComunicacionController::class, 'index'], ['permission:comunicaciones.ver']);
        $router->post('/casos/{casoId}/comunicaciones', [CasoComunicacionController::class, 'store'], ['permission:comunicaciones.crear']);
        $router->post('/casos/{casoId}/comunicaciones/{id}/leer', [CasoComunicacionController::class, 'markRead'], ['permission:comunicaciones.ver']);
        $router->delete('/casos/{casoId}/comunicaciones/{id}', [CasoComunicacionController::class, 'destroy'], ['permission:comunicaciones.eliminar']);
        $router->get('/casos/{casoId}/comunicaciones/email', [CasoComunicacionController::class, 'emailAddress'], ['permission:comunicaciones.ver']);
        $router->get('/casos/{casoId}/partes', [CasoParteController::class, 'index'], ['permission:partes.ver']);
        $router->post('/casos/{casoId}/partes', [CasoParteController::class, 'store'], ['permission:partes.crear']);
        $router->patch('/casos/{casoId}/partes/{id}', [CasoParteController::class, 'update'], ['permission:partes.editar']);
        $router->post('/casos/{casoId}/partes/{id}/eliminar', [CasoParteController::class, 'delete'], ['permission:partes.eliminar']);
        $router->post('/casos/{casoId}/partes/{id}/revelar', [CasoParteController::class, 'reveal'], ['permission:partes.revelar']);
        $router->get('/casos/{casoId}/timeline', [CasoTimelineController::class, 'index'], ['permission:timeline.ver']);
        $router->post('/casos/{casoId}/timeline', [CasoTimelineController::class, 'store'], ['permission:timeline.crear']);
        $router->patch('/casos/{casoId}/timeline/{id}', [CasoTimelineController::class, 'update'], ['permission:timeline.editar']);
        $router->post('/casos/{casoId}/timeline/{id}/visibilidad', [CasoTimelineController::class, 'visibility'], ['permission:timeline.publicar']);
        $router->post('/casos/{casoId}/timeline/{id}/eliminar', [CasoTimelineController::class, 'delete'], ['permission:timeline.eliminar']);

        $router->get('/terminos', [TerminoController::class, 'index'], ['permission:terminos.ver']);
        $router->get('/terminos/{id}', [TerminoController::class, 'show'], ['permission:terminos.ver']);
        $router->post('/terminos', [TerminoController::class, 'store'], ['permission:terminos.crear', 'plan:terminos']);
        $router->patch('/terminos/{id}', [TerminoController::class, 'update'], ['permission:terminos.editar']);
        $router->post('/terminos/{id}/cumplir', [TerminoController::class, 'complete'], ['permission:terminos.cumplir']);
        $router->post('/terminos/{id}/eliminar', [TerminoController::class, 'delete'], ['permission:terminos.eliminar']);

        $router->get('/audiencias', [AudienciaController::class, 'index'], ['permission:audiencias.ver']);
        $router->get('/audiencias/{id}', [AudienciaController::class, 'show'], ['permission:audiencias.ver']);
        $router->post('/audiencias', [AudienciaController::class, 'store'], ['permission:audiencias.crear', 'plan:audiencias']);
        $router->patch('/audiencias/{id}', [AudienciaController::class, 'update'], ['permission:audiencias.editar']);
        $router->post('/audiencias/{id}/resultado', [AudienciaController::class, 'result'], ['permission:audiencias.registrar_resultado']);

        $router->get('/tareas', [TareaController::class, 'index'], ['permission:tareas.ver']);
        $router->get('/tareas/{id}', [TareaController::class, 'show'], ['permission:tareas.ver']);
        $router->post('/tareas', [TareaController::class, 'store'], ['permission:tareas.crear', 'plan:tareas']);
        $router->patch('/tareas/{id}', [TareaController::class, 'update'], ['permission:tareas.editar']);
        $router->post('/tareas/{id}/reasignar', [TareaController::class, 'reassign'], ['permission:tareas.reasignar']);
        $router->post('/tareas/{id}/estado', [TareaController::class, 'status'], ['permission:tareas.cambiar_estado']);

        $router->get('/documentos', [DocumentoController::class, 'index'], ['permission:documentos.ver']);
        $router->get('/documentos/{id}', [DocumentoController::class, 'show'], ['permission:documentos.ver']);
        $router->post('/documentos', [DocumentoController::class, 'store'], ['permission:documentos.cargar', 'plan:documentos']);
        $router->patch('/documentos/{id}', [DocumentoController::class, 'update'], ['permission:documentos.editar']);
        $router->post('/documentos/{id}/eliminar', [DocumentoController::class, 'delete'], ['permission:documentos.eliminar']);
        $router->post('/documentos/{id}/versiones', [DocumentoController::class, 'version'], ['permission:documentos.versionar']);
        $router->get('/documentos/{id}/descargar', [DocumentoController::class, 'download'], ['permission:documentos.descargar']);
        $router->get('/documentos/{id}/versiones/{versionId}/descargar', [DocumentoController::class, 'downloadVersion'], ['permission:documentos.descargar']);

        $router->get('/plantillas', [DocumentTemplateController::class, 'index'], ['permission:plantillas.ver']);
        $router->get('/plantillas/nueva', [DocumentTemplateController::class, 'create'], ['permission:plantillas.crear']);
        $router->post('/plantillas', [DocumentTemplateController::class, 'store'], ['permission:plantillas.crear']);
        $router->get('/plantillas/variables', [DocumentTemplateController::class, 'variables'], ['permission:plantillas.ver']);
        $router->get('/plantillas/para-caso', [DocumentTemplateController::class, 'listForCase'], ['permission:plantillas.ver']);
        $router->post('/plantillas/{id}/generar', [DocumentTemplateController::class, 'generate'], ['permission:plantillas.usar']);
        $router->get('/plantillas/{id}/editar', [DocumentTemplateController::class, 'edit'], ['permission:plantillas.editar']);
        $router->patch('/plantillas/{id}', [DocumentTemplateController::class, 'update'], ['permission:plantillas.editar']);
        $router->delete('/plantillas/{id}', [DocumentTemplateController::class, 'destroy'], ['permission:plantillas.eliminar']);
        $router->get('/integraciones/docusign/conectar', [DocuSignController::class, 'connect'], ['permission:documentos.firmar']);
        $router->get('/integraciones/docusign/callback', [DocuSignController::class, 'callback'], ['permission:documentos.firmar']);
        $router->post('/documentos/{id}/enviar-firma', [DocuSignController::class, 'send'], ['permission:documentos.firmar']);
        $router->get('/documentos/{id}/estado-firma', [DocuSignController::class, 'status'], ['permission:documentos.ver']);

        $router->get('/portal-autorizaciones', [PortalAutorizacionController::class, 'index'], ['permission:portal.autorizar']);
        $router->post('/portal-autorizaciones', [PortalAutorizacionController::class, 'change'], ['permission:portal.autorizar']);
        $router->post('/portal-autorizaciones/{id}/invitar', [PortalAutorizacionController::class, 'invite'], ['permission:portal.autorizar']);

        $router->get('/reportes', [ReporteController::class, 'index'], ['permission:reportes.ver']);
        $router->get('/reportes/aging', [ReporteController::class, 'aging'], ['permission:reportes.ver']);
        $router->get('/reportes/crm', [ReporteController::class, 'crm'], ['permission:reportes.ver']);
        $router->get('/reportes/{tipo}/exportar', [ReporteController::class, 'export'], ['permission:reportes.exportar']);

        $router->get('/importaciones', [ImportacionController::class, 'index'], ['permission:importaciones.ver']);
        $router->post('/importaciones/preview', [ImportacionController::class, 'preview'], ['permission:importaciones.crear']);
        $router->post('/importaciones/{id}/confirmar', [ImportacionController::class, 'confirm'], ['permission:importaciones.ejecutar']);

        $router->get('/soporte', [SoporteController::class, 'index'], ['permission:soporte.ver_propio']);
        $router->get('/soporte/{id}', [SoporteController::class, 'show'], ['permission:soporte.ver_propio']);
        $router->post('/soporte', [SoporteController::class, 'store'], ['permission:soporte.crear']);
        $router->post('/soporte/{id}/mensajes', [SoporteController::class, 'message'], ['permission:soporte.responder']);
        $router->post('/soporte/{id}/estado', [SoporteController::class, 'status'], ['permission:soporte.cambiar_estado']);

        $router->get('/onboarding', [OnboardingController::class, 'index'], ['permission:onboarding.ver']);
        $router->post('/onboarding/actualizar', [OnboardingController::class, 'refresh'], ['permission:onboarding.administrar']);

        $router->get('/finanzas/honorarios', [FinanzasController::class, 'honorarios'], ['permission:finanzas.ver']);
        $router->post('/finanzas/honorarios', [FinanzasController::class, 'storeHonorario'], ['permission:finanzas.crear', 'plan:honorarios']);
        $router->patch('/finanzas/honorarios/{id}', [FinanzasController::class, 'updateHonorario'], ['permission:finanzas.editar']);
        $router->post('/finanzas/honorarios/{id}/cancelar', [FinanzasController::class, 'cancelHonorario'], ['permission:finanzas.anular']);
        $router->post('/finanzas/honorarios/{id}/pdf', [FinanzasController::class, 'invoicePdf'], ['permission:finanzas.ver']);
        $router->post('/finanzas/honorarios/{id}/enviar', [FinanzasController::class, 'sendInvoice'], ['permission:finanzas.crear']);
        $router->post('/finanzas/honorarios/{id}/anular', [FinanzasController::class, 'annulInvoice'], ['permission:finanzas.anular']);
        $router->get('/finanzas/pagos', [FinanzasController::class, 'pagos'], ['permission:finanzas.ver']);
        $router->post('/finanzas/pagos', [FinanzasController::class, 'storePago'], ['permission:finanzas.crear', 'plan:pagos']);
        $router->post('/finanzas/pagos/{id}/revelar', [FinanzasController::class, 'revealPago'], ['permission:finanzas.revelar']);
        $router->post('/finanzas/pagos/{id}/anular', [FinanzasController::class, 'annulPago'], ['permission:finanzas.anular']);
        $router->post('/finanzas/pagos/{id}/reembolsar', [FinanzasController::class, 'refundPago'], ['permission:finanzas.anular']);
        $router->get('/finanzas/gastos', [FinanzasController::class, 'gastos'], ['permission:finanzas.ver']);
        $router->post('/finanzas/gastos', [FinanzasController::class, 'storeGasto'], ['permission:finanzas.crear', 'plan:gastos']);
        $router->patch('/finanzas/gastos/{id}', [FinanzasController::class, 'updateGasto'], ['permission:finanzas.editar']);
        $router->post('/finanzas/gastos/{id}/anular', [FinanzasController::class, 'annulGasto'], ['permission:finanzas.anular']);
        $router->get('/finanzas/saldos', [SaldoController::class, 'index'], ['permission:finanzas.ver']);
        $router->get('/finanzas/trust', [TrustController::class, 'index'], ['permission:trust.ver']);
        $router->get('/finanzas/trust/saldo/{clienteId}', [TrustController::class, 'saldo'], ['permission:trust.ver']);
        $router->get('/finanzas/trust/libro', [TrustController::class, 'libro'], ['permission:trust.ver']);
        $router->get('/finanzas/trust/reporte-conciliacion', [TrustController::class, 'reporteConciliacion'], ['permission:trust.ver']);
        $router->post('/finanzas/trust/deposito', [TrustController::class, 'depositar'], ['permission:trust.depositar']);
        $router->post('/finanzas/trust/retiro', [TrustController::class, 'retirar'], ['permission:trust.retirar']);
    });

    if (Config::get('app.environment') === 'development') {
        $router->get('/health/error', [HealthController::class, 'controlledError']);
    }
};
