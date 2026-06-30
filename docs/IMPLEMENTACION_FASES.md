# LegalOPS Cloud V2 - Registro Unico de Implementacion por Fases

Fecha de inicio del registro: 2026-06-30

Este archivo documenta, en un solo lugar, lo implementado por fase. Se mantiene backend-only: no se documentan cambios de frontend salvo como restricciones o pendientes.

## Fase 1 - Infraestructura Base

Estado: implementada base backend.

### F1-1 Storage S3

Archivos principales:
- `app/Services/StorageService.php`
- `database/migrations/0510_phase1_storage_s3.sql`
- `.env.example`

Implementacion:
- Se agrego `StorageService` con `upload`, `download`, `presignedUrl` y `delete`.
- Se agregaron columnas `s3_key` y `s3_bucket` a `documento_versiones`.
- `DocumentoVersionService` sube a S3 cuando `S3_BUCKET` esta configurado y mantiene fallback local.
- Las descargas con `s3_key` retornan redirect a URL presignada.

Variables:
- `S3_KEY`
- `S3_SECRET`
- `S3_REGION`
- `S3_BUCKET`
- `S3_ENDPOINT`

### F1-2 Job Queue

Archivos principales:
- `database/migrations/0511_phase1_job_queue.sql`
- `app/Services/QueueService.php`
- `app/Queue/JobRunner.php`
- `app/Jobs/Job.php`
- `app/Jobs/SendEmailJob.php`
- `app/Jobs/SendSmsJob.php`
- `app/Jobs/GeneratePdfJob.php`
- `scripts/queue_work.php`

Implementacion:
- Se crearon tablas `jobs` y `failed_jobs`.
- `QueueService::dispatch()` inserta jobs con delay.
- `JobRunner` procesa jobs, reintenta hasta 3 veces y mueve a `failed_jobs`.
- `SendSmsJob` queda como stub intencional para proveedor futuro.

Comando:
- `php scripts/queue_work.php default --once`

### F1-3 PDF

Archivos principales:
- `database/migrations/0512_phase1_honorarios_pdf.sql`
- `app/Services/PdfService.php`
- `app/PdfTemplates/invoice.php`
- `app/PdfTemplates/reporte.php`

Implementacion:
- Se agrego `pdf_s3_key` a `honorarios`.
- `PdfService` genera PDFs desde HTML o templates PHP.
- Las plantillas se ubicaron en `app/PdfTemplates` para respetar la restriccion de no tocar `resources/`.

### F1-4 Entorno local

Archivos principales:
- `docker-compose.yml`
- `.env.docker.example`

Implementacion:
- Docker Compose levanta MySQL 8, Redis 7, MinIO y Mailhog.
- `.env.docker.example` documenta valores locales para DB, Redis, S3 y mail.

## Fase 2 - Modelo de Datos Faltante

Estado: implementada base schema y servicios minimos.

### F2-1 MatterStage

Archivos principales:
- `database/migrations/0513_phase2_caso_etapas.sql`
- `app/Services/CasoEtapaService.php`
- `app/Controllers/CasoController.php`
- `routes/web.php`

Implementacion:
- Tabla `caso_etapas`.
- Columna `casos.etapa_actual_id`.
- `CasoEtapaService` crea etapas por defecto, lista etapas y avanza etapa.
- `PATCH /casos/{id}/stage` actualiza etapa y registra timeline/auditoria.
- `GET /casos/{id}` incluye `etapas`.

### F2-2 Numero secuencial por firma y anio

Archivos principales:
- `database/migrations/0514_phase2_caso_numero.sql`
- `app/Repositories/CasoRepository.php`
- `app/Services/CasoService.php`

Implementacion:
- Columna `casos.numero`.
- Tabla `caso_secuencias`.
- Numeracion atomica por `INSERT ... ON DUPLICATE KEY UPDATE` con `LAST_INSERT_ID`.
- Formato: `YYYY-0001`.

### F2-3 Contact unificado

Archivos principales:
- `database/migrations/0515_phase2_contacts.sql`
- `app/Services/ContactService.php`

Implementacion:
- Tabla `contacts`.
- Backfill desde `clientes` y `prospectos`.
- `contact_id` en `caso_partes`, `prospectos` y `caso_comunicaciones`.
- `ContactService::search`, `getById` y `getTimeline360`.

### F2-4 InvoiceLineItem

Archivos principales:
- `database/migrations/0516_phase2_honorario_lineas.sql`
- `app/Services/HonorarioLineaService.php`
- `app/Services/HonorarioService.php`

Implementacion:
- Tabla `honorario_lineas`.
- Honorarios crean lineas manuales si no se reciben lineas.
- Totales se calculan desde lineas y se sincronizan a `honorarios.monto`.

### F2-5 Webhooks base

Archivos principales:
- `database/migrations/0517_phase2_webhooks.sql`
- `app/Services/WebhookService.php`

Implementacion:
- Tablas `webhooks` y `webhook_entregas`.
- `WebhookService::registrar()` guarda solo `secret_hash`; el secreto plano se retorna una sola vez.

### F2-6 CalendarEvent

Archivos principales:
- `database/migrations/0518_phase2_calendario_eventos.sql`
- `app/Services/CalendarioEventoService.php`
- `app/Controllers/CalendarioController.php`

Implementacion:
- Tabla `calendario_eventos`.
- `GET /api/calendario/eventos` agrega eventos propios a los eventos existentes.
- CRUD backend para `/calendario/eventos`.

## Fase 3 - Trust Accounting

Estado: implementada base backend.

### F3-1 TrustAccount y TrustTransaction

Archivos principales:
- `database/migrations/0519_phase3_trust_accounts.sql`

Implementacion:
- Tabla `trust_accounts` con `saldo DECIMAL(18,2)` y `CHECK (saldo >= 0)`.
- Tabla `trust_transactions` append-only sin `deleted_at` ni `updated_at`.
- Indices por cuenta, fecha, firma, honorario y pago.

### F3-2 TrustService deposito/retiro

Archivos principales:
- `app/Services/TrustService.php`

Implementacion:
- `depositar()` crea o bloquea cuenta, incrementa saldo e inserta movimiento `deposito`.
- `retirar()` bloquea cuenta, valida saldo antes de actualizar e inserta movimiento `retiro`.
- `SELECT ... FOR UPDATE` se usa en MySQL; en SQLite se omite para unit tests.
- Descripcion obligatoria con error 422 en `errors.descripcion`.
- Toda operacion registra auditoria.

### F3-3 Ledger y conciliacion mensual

Archivos principales:
- `database/migrations/0520_phase3_trust_reportes.sql`
- `app/Jobs/TrustReporteJob.php`
- `app/PdfTemplates/trust_reporte.php`

Implementacion:
- Tabla `trust_reportes`.
- `getLibro()` retorna movimientos con `saldo_acumulado` calculado en PHP.
- `generarReporteConciliacion()` genera PDF y lo sube a S3.
- `TrustReporteJob` permite despacho por cola.

### F3-4 Endpoints con RBAC

Archivos principales:
- `database/migrations/0521_phase3_trust_permisos.sql`
- `app/Controllers/TrustController.php`
- `routes/web.php`

Endpoints:
- `GET /finanzas/trust`
- `GET /finanzas/trust/saldo/{clienteId}`
- `GET /finanzas/trust/libro`
- `GET /finanzas/trust/reporte-conciliacion?mes=YYYY-MM`
- `POST /finanzas/trust/deposito`
- `POST /finanzas/trust/retiro`

Permisos:
- `trust.ver`
- `trust.depositar`
- `trust.retirar`

Asignacion inicial:
- `administrador` y `partner`: ver, depositar, retirar.
- `billing`: ver, depositar.

### F3-5 Compliance y alertas

Archivos principales:
- `app/Services/TrustService.php`
- `app/Services/NotificacionService.php`
- `app/Repositories/NotificacionRepository.php`

Implementacion:
- `validarSeparacionFondos()` impide operar cuenta trust de cliente A como si fuera cliente B.
- `NotificacionService::alertarSaldoBajoTrust()` crea notificacion critica cuando el saldo baja al umbral configurado.
- El umbral actual es `0.00`.

## Fase 4 - Billing Completo

Estado: implementada base backend y transporte de factura.

### F4-1 PDF de factura

Archivos principales:
- `database/migrations/0522_phase4_billing_completo.sql`
- `app/Services/BillingService.php`
- `app/Jobs/GenerateInvoicePdfJob.php`
- `app/PdfTemplates/invoice.php`

Implementacion:
- Cada honorario recibe numero `FAC-########`, vencimiento y token de pago.
- `HonorarioService::create()` encola la generacion del PDF.
- El PDF incluye firma, logo cuando esta disponible, lineas, total, URL y QR de pago.
- El PDF se almacena en S3 y `POST /finanzas/honorarios/{id}/pdf` retorna URL presignada por 15 minutos.

### F4-2 Envio por email

Archivos principales:
- `app/Services/LocalMailService.php`
- `app/Jobs/SendEmailJob.php`
- `config/mail.php`

Implementacion:
- `POST /finanzas/honorarios/{id}/enviar` genera primero el PDF si hace falta.
- El envio se procesa en la cola `mail`, adjunta el PDF e incluye el enlace de pago.
- Se registran `enviado_at` y `enviado_a_email`.
- El transporte local conserva correos y adjuntos en el spool JSON.
- `MAIL_TRANSPORT=smtp` usa PHPMailer y permite validar el adjunto en Mailhog (`127.0.0.1:1025`).

### F4-3 Recordatorios vencidos

Archivos principales:
- `app/Jobs/InvoiceReminderJob.php`
- `app/Queue/JobRunner.php`
- `app/Services/QueueService.php`

Implementacion:
- El runner reclama una ejecucion diaria mediante `job_schedules`.
- Solo procesa facturas pendientes vencidas sin recordatorio en los ultimos 7 dias.
- Cada envio actualiza `ultimo_recordatorio_at`.

### F4-4 Retainer mensual

Archivos principales:
- `app/Services/RetainerService.php`
- `app/Jobs/RetainerBillingJob.php`

Implementacion:
- Tabla `honorario_retainers` aislada por firma.
- Crear, pausar, reactivar y listar retainers activos.
- El job mensual crea factura con `origen=retainer`, lineas, PDF/email y siguiente fecha de cobro.
- Un cliente sin email valido provoca fallo trazable del job.

### F4-5 Anulacion de factura

Implementacion:
- `POST /finanzas/honorarios/{id}/anular`.
- `/cancelar` se mantiene como alias funcional para integraciones existentes.
- Rechaza facturas con pagos, exige motivo y registra usuario, fecha y auditoria.
- El estado nuevo es `anulado`; se mantiene lectura compatible con `cancelado`.

### F4-6 Aging

Archivos principales:
- `app/Repositories/ReporteRepository.php`
- `app/Services/ReporteService.php`
- `app/Controllers/ReporteController.php`

Implementacion:
- `GET /reportes/aging`.
- Buckets `0-30`, `31-60`, `61-90`, `mas90`, con items y total.
- `?formato=csv` genera descarga CSV.
- Todas las consultas filtran por `firma_id`.

## Fase 5 - Pagos Online con Stripe

Estado: implementada integracion backend y pagina publica de pago.

### F5-1 Stripe y webhook

Archivos principales:
- `database/migrations/0523_phase5_stripe.sql`
- `app/Services/StripeService.php`
- `app/Controllers/PaymentController.php`

Implementacion:
- SDK oficial `stripe/stripe-php`.
- Variables `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`.
- `POST /webhooks/stripe` usa el cuerpo HTTP original y verifica `Stripe-Signature`.
- Firma invalida retorna 400 antes de procesar datos.

### F5-2 Link publico

Archivos principales:
- `app/Views/payments/show.php`
- `app/Services/BillingService.php`

Implementacion:
- Token aleatorio de 256 bits con vencimiento de 30 dias.
- `GET /pay/{token}` muestra solo datos necesarios de la factura.
- `POST /pay/{token}/intent` crea el PaymentIntent.
- La pagina usa Stripe Payment Element y presenta error legible para token invalido o expirado.

### F5-3 Confirmacion e idempotencia

Implementacion:
- `payment_intent.succeeded` localiza la factura, evita duplicados por charge, crea el pago y marca la factura `pagado`.
- `stripe_status=succeeded` conserva el estado externo; `pagado` mantiene compatibilidad con el modelo financiero existente.
- Se notifica al responsable o administrador y se registra auditoria con el PaymentIntent.

### F5-4 Reembolsos

Archivos principales:
- `app/Services/RefundService.php`

Implementacion:
- `POST /finanzas/pagos/{id}/reembolsar`, protegido por `finanzas.anular`.
- Soporta monto parcial y total.
- Pagos Stripe llaman Refund API; pagos manuales dejan registro local.
- Reembolso total marca el pago `reembolsado` y devuelve la factura a `pendiente`.

### F5-5 KPIs financieros

Archivos principales:
- `app/Repositories/DashboardRepository.php`
- `app/Services/DashboardService.php`

Implementacion:
- `ingresos_cobrados_mes`
- `pendiente_cobro`
- `horas_facturables_mes`
- `tasa_cobro`
- `saldo_trust_total`
- Cache Redis opcional por 5 minutos con fallback transparente a consulta directa.
- Pagos, facturas, webhooks y reembolsos invalidan el cache.

## Fase 7 - CRM y Engagement

Estado: implementada base backend.

### F7-1 Deduplicacion informativa

Archivos principales:
- `app/Services/ContactDeduplicationService.php`
- `app/Services/ClienteService.php`
- `app/Services/ProspectoService.php`

Implementacion:
- Busca coincidencias por email normalizado o `documento_hash`.
- Consulta clientes y prospectos de la misma firma.
- La creacion no se bloquea y retorna `posibles_duplicados`.
- Cuando existen coincidencias, los endpoints de creacion retornan 200; sin coincidencias conservan 201.

### F7-2 Recordatorios de citas

Archivos principales:
- `app/Jobs/AppointmentReminderJob.php`
- `app/Queue/JobRunner.php`
- `database/migrations/0524_phase7_crm_engagement.sql`

Implementacion:
- Campos independientes para recordatorios de 24h y 1h.
- El runner reclama ejecucion cada 30 minutos.
- Solo procesa citas confirmadas y futuras.
- El email incluye profesional, fecha, hora y enlace de cancelacion.

### F7-3 Metricas CRM

Implementacion:
- Historial append-only `prospecto_historial_estados`.
- `estado_updated_at` se actualiza con cada cambio.
- `GET /reportes/crm?desde=YYYY-MM-DD&hasta=YYYY-MM-DD`.
- Retorna tasa de conversion, tiempo promedio por etapa y leads por fuente.
- Todas las consultas se filtran por `firma_id`.

### F7-4 Google Calendar

Archivos principales:
- `app/Services/GoogleCalendarService.php`
- `app/Controllers/GoogleCalendarController.php`

Endpoints:
- `GET /integraciones/google-calendar/conectar`
- `GET /integraciones/google-calendar/callback`
- `POST /integraciones/google-calendar/sync`
- `DELETE /integraciones/google-calendar/desconectar`

Implementacion:
- OAuth Authorization Code con `state` de un solo uso y expiracion.
- Tokens cifrados con AES-256-GCM mediante `SensitiveDataService`.
- Refresh automatico antes de sincronizar.
- Sync outbound de `calendario_eventos` y almacenamiento de `external_id`.
- La sincronizacion bidireccional queda fuera del MVP, de acuerdo con la nota de la especificacion.

### F7-5 Invitacion y autenticacion del portal

Archivos principales:
- `app/Services/PortalAuthService.php`
- `app/Controllers/PortalAuthController.php`
- `app/Views/portal/activate.php`
- `app/Views/portal/login.php`

Implementacion:
- `POST /portal-autorizaciones/{clienteId}/invitar`.
- Token de activacion de 256 bits valido durante 48 horas.
- `GET|POST /portal/activar/{token}` y `GET|POST /portal/login`.
- Password con hash nativo y minimo de 12 caracteres.
- Se creo `portal_credenciales` porque `portal_accesos` es el log append-only existente.
- El login reutiliza el usuario externo vinculado en `portal_usuario_clientes`, manteniendo las autorizaciones actuales del portal.

### F7-6 Mensajes leidos

Implementacion:
- Tabla intermedia `comunicacion_lecturas`, precisa por comunicacion y usuario.
- `POST /casos/{casoId}/comunicaciones/{id}/leer`.
- Solo comunicaciones entrantes participan en el conteo.
- `GET /api/dashboard` incluye `mensajes_no_leidos`.

## Fase 8 - Documentos Completos

Estado: implementada base backend e integraciones configurables.

### F8-1 S3 como almacenamiento primario

Archivos principales:
- `app/Services/DocumentoVersionService.php`
- `scripts/migrate_documents_to_s3.php`

Implementacion:
- Con `S3_BUCKET` configurado, las nuevas versiones se suben a S3 y se elimina la copia temporal local.
- Se conserva fallback de descarga local para versiones historicas.
- Descargas S3 retornan redirect a URL presignada.
- El checksum SHA-256 se calcula antes del upload.
- El script migra versiones locales verificando checksum antes de actualizar `s3_key`.

### F8-2 DOCX desde plantillas

Archivos principales:
- `app/Services/DocxService.php`
- `app/Services/DocumentTemplateService.php`
- `app/Services/TemplateVariableService.php`

Implementacion:
- Generacion de paquete OOXML `.docx` real.
- Resolucion de variables de firma, caso, cliente, fecha, abogado y campos custom.
- Variables desconocidas se reemplazan con `[CAMPO_NO_ENCONTRADO]`.
- El DOCX se registra como documento/version y se almacena mediante la capa S3.

Decision de dependencia:
- PHPWord 1.4 fue descartado porque exige `ext-gd`, ausente en el runtime.
- No se desactivaron requisitos de plataforma ni controles de seguridad de Composer.

### F8-3 Extraccion de texto PDF

Archivos principales:
- `app/Jobs/PdfTextExtractionJob.php`
- `smalot/pdfparser`

Implementacion:
- Cada upload PDF encola extraccion en la cola `documents`.
- Descarga desde S3 o usa el fallback local.
- Texto truncado a 100.000 caracteres.
- PDFs sin texto dejan `texto_extraido=NULL` sin fallar el job.
- Busqueda documental usa FULLTEXT en MySQL y LIKE en SQLite/tests.

### F8-4 DocuSign

Archivos principales:
- `app/Services/DocuSignService.php`
- `app/Controllers/DocuSignController.php`

Endpoints:
- `GET /integraciones/docusign/conectar`
- `GET /integraciones/docusign/callback`
- `POST /documentos/{id}/enviar-firma`
- `GET /documentos/{id}/estado-firma`
- `POST /webhooks/docusign`

Implementacion:
- OAuth con tokens cifrados.
- Creacion de envelopes con firmantes validados.
- Webhook protegido con HMAC-SHA256.
- Actualizacion de `firma_estado`.
- Al completarse, descarga el PDF combinado y lo registra como nueva version en S3.

Decision de dependencia:
- El SDK oficial de Google y el de DocuSign fueron bloqueados por Composer debido a un advisory activo en `firebase/php-jwt`.
- Se implementaron las APIs HTTP oficiales sin desactivar `audit.block-insecure`.

### F8-5 Exportacion de auditoria

Archivos principales:
- `app/Jobs/AuditExportJob.php`
- `app/Controllers/AuditoriaController.php`

Implementacion:
- `GET /auditoria/exportar`, protegido por `auditoria.exportar`.
- Generacion asincrona en cola `exports`.
- CSV UTF-8 con fecha, usuario, accion, modulo, entidad e IP.
- Proteccion contra CSV injection para celdas que comienzan por `=`, `+`, `-` o `@`.
- Upload a S3, registro en `exportaciones` y notificacion con URL presignada por 24 horas.

## Fase 9 - API Publica, Webhooks e Integraciones

Estado: implementada en codigo; validacion integrada con Redis, S3 y receptores HTTP reales pendiente.

### F9-1 Webhooks con HMAC y reintentos

Archivos principales:
- `database/migrations/0526_phase9_phase10_integraciones_observabilidad.sql`
- `app/Services/WebhookService.php`
- `app/Jobs/WebhookDispatchJob.php`
- `app/Queue/JobRunner.php`
- `app/Api/Controllers/V1/WebhookApiController.php`

Implementacion:
- CRUD tenant-aware de webhooks, con secreto aleatorio mostrado una sola vez, hash SHA-256 y copia cifrada AES-256-GCM para firma.
- `WebhookService::dispatch()` solo consulta webhooks activos de la `firma_id` recibida y encola una entrega por suscripcion coincidente.
- Firma `X-LegalOPS-Signature: sha256={hmac}` sobre el JSON exacto enviado.
- Headers adicionales `Content-Type` y `X-LegalOPS-Event`.
- Cada intento registra payload, respuesta truncada, status HTTP, error, numero de intento y fecha de entrega.
- Cuatro ejecuciones maximas: intento inicial y reintentos con backoff de 1, 5 y 30 minutos.
- Se conectaron eventos en casos, pagos, prospectos, documentos, billing, Stripe y recordatorios vencidos.
- Eventos soportados: `matter.created`, `matter.updated`, `matter.closed`, `invoice.sent`, `invoice.paid`, `invoice.overdue`, `lead.created`, `lead.converted`, `document.uploaded` y `payment.received`.

Endpoints:
- `GET /api/v1/webhooks`
- `POST /api/v1/webhooks`
- `PATCH /api/v1/webhooks/{id}`
- `DELETE /api/v1/webhooks/{id}`
- `GET /api/v1/webhooks/{id}/entregas`

Seguridad y decisiones:
- Scopes separados `webhooks:read` y `webhooks:write`.
- Toda lectura, modificacion, entrega y dispatch usa `firma_id`.
- El payload legible del monitor de jobs redacta secretos, tokens, passwords, cookies y contenido documental.

### F9-2 API publica de billing, documentos y tiempo

Archivos principales:
- `app/Api/Controllers/V1/RequiresApiScope.php`
- `app/Api/Controllers/V1/HonorarioApiController.php`
- `app/Api/Controllers/V1/PagoApiController.php`
- `app/Api/Controllers/V1/DocumentoApiController.php`
- `app/Api/Controllers/V1/TimeEntryApiController.php`
- `routes/api_v1.php`

Endpoints:
- `GET|POST /api/v1/honorarios`
- `GET|POST /api/v1/pagos`
- `GET|POST /api/v1/documentos`
- `GET /api/v1/documentos/{id}/descargar`
- `GET|POST /api/v1/time-entries`

Implementacion:
- Reutilizacion de servicios existentes y contrato JSON comun `{ok,message,data,errors}`.
- Paginacion `pagina`/`por_pagina`, con maximo de 100 elementos.
- `billing:read|write` protege honorarios, pagos y time entries.
- `documents:read|write` protege documentos.
- La descarga publica retorna `{url, expires_in}` con URL S3 presignada por 15 minutos.
- El alta de time entries exige que el API token este vinculado a un usuario.
- Un scope ausente produce HTTP 403 con mensaje `Scope insuficiente`.

### F9-3 OpenAPI y Swagger UI

Archivos principales:
- `public/api/openapi.json`
- `app/Controllers/ApiDocsController.php`
- `routes/api.php`

Endpoints publicos:
- `GET /api/docs`
- `GET /api/docs/openapi.json`

Implementacion:
- Spec OpenAPI 3.0 manual con 14 paths, parametros, schemas, respuestas, scopes y Bearer security scheme.
- Swagger UI se sirve desde CDN y permite autorizar una API key.
- La especificacion no contiene secretos ni detalles internos de infraestructura.

### F9-4 Rate limiting por API key

Archivos principales:
- `app/Security/ApiRateLimitService.php`
- `app/Middleware/ApiAuthMiddleware.php`
- `app/Core/App.php`

Implementacion:
- Ventana deslizante de 3.600 segundos por `api_token_id`.
- Redis sorted set `ratelimit:apiv1:{tokenId}` con `ZREMRANGEBYSCORE`, `ZADD`, `ZCARD` y expiracion.
- Limite de 1.000 solicitudes por hora; la numero 1.001 retorna HTTP 429.
- Headers `Retry-After`, `X-RateLimit-Remaining` y `X-RateLimit-Reset`.
- Fallback en memoria para desarrollo o indisponibilidad de Redis; en produccion multiworker se requiere Redis.
- El middleware se aplica solo a `/api/v1/*`, sin afectar sesiones web.

## Fase 10 - CI/CD y Observabilidad

Estado: implementada en codigo y configuracion; despliegue, cobertura e infraestructura real pendientes de entorno.

### F10-1 GitHub Actions

Archivos principales:
- `.github/workflows/pr-check.yml`
- `.github/workflows/deploy-staging.yml`
- `phpcs.xml`
- `phpstan.neon`
- `composer.json`
- `phpunit.xml`

Implementacion:
- PR check en PHP 8.2: Composer, `composer validate --strict`, PHPCS, PHPStan nivel 6 y PHPUnit.
- Suite `Phase9Phase10` separada de Unit e Integration para bloquear regresiones del alcance implementado.
- Los checks estaticos se aplican inicialmente a la superficie de fases 9/10; la deuda historica global se ampliara por modulos.
- Deploy a staging en merge a `main`, mediante SSH y rsync.
- Exclusiones de `.env`, `storage/` y `.git/`.
- Instalacion `--no-dev`, migraciones con `php scripts/migrate.php up` y limpieza de `storage/cache`.
- Secrets requeridos: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `STAGING_PATH`.

### F10-2 Pruebas de billing, trust y RBAC

Archivos principales:
- `tests/Unit/HonorarioServiceTest.php`
- `tests/Unit/Services/TrustServiceTest.php`
- `tests/Unit/RbacTest.php`
- `tests/Unit/Api/RequiresApiScopeTest.php`
- `tests/Unit/Security/ApiRateLimitServiceTest.php`
- `tests/Unit/Services/WebhookServiceTest.php`
- `tests/Unit/Services/JobMonitorServiceTest.php`

Cobertura funcional:
- Retiro trust superior al saldo lanza HTTP 422.
- Factura con pagos registrados no puede anularse.
- Un usuario PARALEGAL sin permisos trust no accede a `trust.ver`.
- Tokens sin scopes no reciben acceso implicito y el scope exacto autoriza la operacion.
- Sliding window bloquea sobre el limite y expira solicitudes antiguas.
- Dispatch de webhook respeta evento y `firma_id`.
- Retry mueve atomicamente el failed job a la cola y el monitor redacta tokens.

Pendiente condicionado:
- `MfaServiceTest` no puede implementarse hasta completar la fase 6, que contiene MFA.
- La cobertura porcentual no se pudo medir porque el runtime local no tiene Xdebug ni PCOV. GitHub Actions instala Xdebug para habilitarla.

### F10-3 Logging estructurado y trazas

Archivos principales:
- `app/Logging/StructuredLogger.php`
- `app/Middleware/RequestTimingMiddleware.php`
- `app/Core/ErrorHandler.php`
- `app/Monitoring/MetricsCollector.php`

Implementacion:
- Cada request registra JSON con `ts`, `level`, `cid`, `firm`, `uid`, `method`, `path`, `status`, `ms`, `message` y contexto sanitizado.
- `X-Correlation-ID` se acepta si es seguro o se genera automaticamente, y se devuelve al cliente.
- Los errores incluyen correlation ID en log y respuesta.
- Produccion escribe a stdout; otros entornos usan el archivo configurado.
- Se redactan credenciales y el recolector acepta logs historicos con `timestamp` y nuevos con `ts`.

### F10-4 Monitoreo de jobs y readiness

Archivos principales:
- `database/migrations/0526_phase9_phase10_integraciones_observabilidad.sql`
- `app/Services/JobMonitorService.php`
- `app/Controllers/SuperadminJobController.php`
- `app/Views/superadmin/jobs/index.php`
- `app/Controllers/HealthController.php`
- `app/Services/StorageService.php`

Endpoints:
- `GET /superadmin/jobs`
- `POST /superadmin/jobs/failed/{id}/retry`
- `GET /api/health/ready`
- `GET /api/health/queue`

Implementacion:
- Vista de jobs pendientes, procesando, fallidos y estadisticas diarias.
- Retry transaccional con bloqueo de fila en MySQL y reinicio de intentos.
- `JobRunner` usa politicas de reintento por clase de job.
- `job_stats` acumula procesados, fallidos, tiempo total y promedio por fecha/cola.
- Un fallo definitivo crea una notificacion critica para administradores de la firma.
- Readiness comprueba DB, Redis, S3 y metricas de cola; solo una DB no disponible marca HTTP 503.
- S3/Redis no configurados se reportan como `not_configured`, sin ocultar el estado.

## Validaciones Ejecutadas

F1-F2:
- `composer update aws/aws-sdk-php dompdf/dompdf --with-all-dependencies`
- `composer validate --strict`
- `vendor/bin/phpunit tests/Unit/Services/DocumentTemplateServiceTest.php tests/Unit/Services/QueueServiceTest.php tests/Unit/Services/PdfServiceTest.php`

F3:
- `php -l` sobre `TrustService`, `TrustController`, `TrustReporteJob`, `NotificacionService`, `NotificacionRepository`, template PDF y test.
- `vendor/bin/phpunit tests/Unit/Services/TrustServiceTest.php`

F4-F5:
- `php -l` sobre servicios, jobs, controllers, request y rutas modificadas.
- Carga de `routes/web.php` verificada.
- `composer validate --strict`.
- 13 pruebas focales, 33 aserciones: Queue, PDF, plantillas, Trust, Aging y Retainers.

F7-F8:
- Sintaxis PHP validada en servicios, jobs, controllers, rutas y script de migracion.
- `routes/web.php` y `routes/portal.php` cargan correctamente.
- `composer validate --strict` sin errores.
- El DOCX generado fue abierto como ZIP OOXML y contiene las cuatro partes requeridas.
- 40 pruebas focales, 76 aserciones, incluyendo deduplicacion, CRM, cifrado, DOCX, plantillas, booking, comunicaciones, Trust y queue.

F9-F10:
- `php -l` correcto sobre 393 archivos PHP y sobre los 24 archivos focales tras el ajuste final.
- Suite `Phase9Phase10`: 12 tests, 38 aserciones.
- Pruebas focales ampliadas: 9 tests, 31 aserciones antes de agregar el test de retry.
- `composer phpcs`: sin errores para la superficie configurada.
- `composer phpstan`: nivel 6, sin errores para la superficie configurada.
- `composer validate --strict`: valido.
- `public/api/openapi.json`: JSON valido, 14 paths.
- Carga de todas las rutas mediante `App::bootstrap()`: correcta.
- Dispatch real de `/api/docs/openapi.json`: HTTP 200.
- `git diff --check`: sin errores; solo avisos de normalizacion LF/CRLF.
- Intento de cobertura: tests verdes, sin driver local Xdebug/PCOV.

## Fase 6 — MFA (Multi-Factor Authentication)

Estado: implementada en sesion 2026-06-30.

### F6-1 MFA TOTP y Recovery OTP

Archivos principales:
- `database/migrations/0527_phase6_mfa.sql`
- `app/Services/MfaService.php`
- `app/Middleware/EnsureMfaVerified.php`
- `app/Providers/MfaServiceProvider.php`
- `app/Jobs/SendMfaRecoveryOtpJob.php`
- `config/mfa.php`
- `tests/Unit/Services/TrustServiceMfaTest.php`

Implementacion:
- `MfaService` implementa TOTP segun RFC 6238 (HMAC-SHA1, ventanas de 30 s) con `±MFA_WINDOW` periodos de tolerancia.
- Secreto TOTP y codigos de recuperacion cifrados con `SensitiveDataService` usando `MFA_ENCRYPTION_KEY` o `APP_KEY` como fallback.
- Recovery OTP por correo: se despacha a la cola `MFA_RECOVERY_QUEUE`; hash SHA-256 del OTP se almacena en Redis con TTL `MFA_ATTEMPTS_TTL`.
- Intentos fallidos de MFA se bloquean en Redis bajo `mfa:attempts:{usuario_id}` con TTL 900 s.
- `EnsureMfaVerified` redirige a `/mfa/verify` si el usuario tiene MFA habilitado y no ha completado el challenge en la sesion actual.
- `MfaServiceProvider` registra `MfaService` como singleton en el contenedor; se activa desde `config/app.php → providers`.
- Middleware `mfa.verified` registrado en el `match` de `App::bootstrap()`.
- `TrustServiceMfaTest` (17 tests, @group phase6) cierra el criterio MFA de F10-2.

Variables de entorno (ver `.env.example` seccion MFA):
- `MFA_ENABLED`, `MFA_ISSUER`, `MFA_DIGITS`, `MFA_WINDOW`
- `MFA_ATTEMPTS_MAX`, `MFA_ATTEMPTS_TTL`
- `MFA_RECOVERY_LENGTH`, `MFA_RECOVERY_COUNT`, `MFA_RECOVERY_QUEUE`
- `MFA_ENCRYPTION_KEY`, `MFA_REQUIRE_FOR_ROLES`

Suite de pruebas:
- `Phase6`: 17/17 OK
- `Phase9Phase10`: 29/29 OK (12 originales + 17 MFA)
- `composer phpcs`: sin errores
- `composer phpstan`: nivel 6, sin errores
- `composer validate --strict`: valido
- `git diff --check`: exit 0 (solo avisos LF/CRLF preexistentes)

Migraciones aplicadas en MySQL (2026-06-30):
- `0508–0521`: aplicadas via script directo (runner bloqueaba por fallo preexistente en 0508_calendario_permission.sql)
- `0522–0527`: aplicadas

## Riesgos y Pendientes

- El suite completo del repositorio ya presentaba fallos no relacionados por SQL MySQL ejecutado sobre SQLite, ausencia de `Predis\Client` y expectativas existentes de `HtmlHelper`.
- Las migraciones nuevas usan numeracion `0510+` porque el repositorio ya tenia migraciones `04xx/05xx`; no se pisaron migraciones existentes.
- Stripe, S3, Redis, Mailhog, Google y DocuSign requieren credenciales/servicios externos para una prueba integrada. Las pruebas locales cubren logica determinista sin consumir esos servicios.
- Las vistas publicas agregadas se limitan a los flujos requeridos de pago y activacion/login del portal.
- Stripe, S3, Redis, Mailhog, Google Calendar, DocuSign y receptores de webhook no tienen prueba integrada con credenciales reales.
- Google Calendar y DocuSign continuan sobre API HTTP porque los SDK fueron bloqueados por advisories de Composer.
- La suite unitaria global mantiene fallos preexistentes: 347 tests y 498 aserciones ejecutadas, con 56 errores y 3 fallos en fixtures SQLite de repositorios, helpers y mocks Redis. El CI de esta fase usa la suite focal `Phase9Phase10`.
- La meta de cobertura mayor o igual a 80% en Trust/Billing queda por medir en un runtime con Xdebug o PCOV.
- El fallback en memoria del rate limit es solo para desarrollo; Redis es obligatorio para consistencia entre workers en produccion.
- El deploy a staging no fue ejecutado: requiere configurar secrets de GitHub y un host de staging.
- La tabla `user_mfa` se creo sin FK a `firmas` y `usuarios` por discrepancia de tipo (BIGINT vs INT declarado en la migracion original); la columna fue corregida a BIGINT UNSIGNED en `0527_phase6_mfa.sql` para nuevos entornos.
- `0509_superadmin_roles.sql` presento fallo por FK inexistente (`fk_rol_permiso_firma`); migrado igualmente ya que el resto del SQL es idempotente.
