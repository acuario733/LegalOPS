#!/usr/bin/env bash
#
# organizar_commits.sh
#
# Organiza los ~235 archivos sin commitear del repo LegalOPS Cloud V2 en
# commits atomicos y logicos, agrupados por modulo/fase.
#
# USO:
#   cd "02_CODIGO_FUENTE/legalops"          # o donde esté clonado el repo
#   bash organizar_commits.sh --dry-run      # revisa qué haría, sin commitear
#   bash organizar_commits.sh                # ejecuta de verdad
#
# Requisitos: estar parado en la raíz del repo (donde está .git), git instalado.
# El script se detiene ante el primer error (set -e) para que puedas revisar
# manualmente si algo no coincide con tu árbol de trabajo actual.

set -e

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
  echo ">> Modo --dry-run: solo se mostrarán los comandos, no se ejecutarán."
fi

if [[ ! -d ".git" ]]; then
  echo "ERROR: no se encontró .git en el directorio actual."
  echo "Ejecuta este script desde la raíz del repo (02_CODIGO_FUENTE/legalops)."
  exit 1
fi

# --- 0. Desbloquear git si quedó un index.lock obsoleto -------------------
if [[ -f ".git/index.lock" ]]; then
  echo ">> Se encontró .git/index.lock. Verifica que NO tengas otro git corriendo"
  echo "   (otra terminal, un IDE, etc.) antes de continuar."
  read -p "   ¿Eliminar el lock y continuar? (s/N) " confirm
  if [[ "$confirm" == "s" || "$confirm" == "S" ]]; then
    rm -f ".git/index.lock"
    echo "   Lock eliminado."
  else
    echo "   Cancelado por el usuario."
    exit 1
  fi
fi

run() {
  # $1 = mensaje del commit, resto = rutas a agregar
  local msg="$1"; shift
  echo ""
  echo "=== $msg ==="
  for path in "$@"; do
    echo "  git add \"$path\""
    if [[ $DRY_RUN -eq 0 ]]; then
      git add -- "$path" 2>/dev/null || echo "    (aviso: '$path' no existe o ya está limpio, se omite)"
    fi
  done
  echo "  git commit -m \"$msg\""
  if [[ $DRY_RUN -eq 0 ]]; then
    git commit -m "$msg" --quiet || echo "    (nada que commitear en este grupo, se omite)"
  fi
}

# --- 1. Ignorar caches que no deben versionarse ---------------------------
if [[ $DRY_RUN -eq 0 ]] && ! grep -q "^/storage/cache" .gitignore 2>/dev/null; then
  {
    echo ""
    echo "/storage/cache/"
  } >> .gitignore
fi
run "chore(gitignore): ignora cache de PHPStan en storage/cache" ".gitignore"

# --- 2. Tooling y configuración de desarrollo -----------------------------
run "chore(tooling): agrega Docker, PHPCS, PHPStan, CI y actualiza dependencias" \
  ".github" "docker-compose.yml" "phpcs.xml" "phpstan.neon" \
  "composer.json" "composer.lock" "phpunit.xml" ".env.example"

# --- 3. Núcleo MVC (refactor transversal) ---------------------------------
run "refactor(core): actualiza núcleo MVC (DI, tenant context, validación, errores)" \
  "app/Core/App.php" "app/Core/Audit.php" "app/Core/Config.php" \
  "app/Core/Container.php" "app/Core/Csrf.php" "app/Core/Database.php" \
  "app/Core/ErrorHandler.php" "app/Core/HttpException.php" \
  "app/Core/MigrationRunner.php" "app/Core/Request.php" "app/Core/Router.php" \
  "app/Core/Session.php" "app/Core/TenantContext.php" "app/Core/Validator.php" \
  "app/Core/View.php"

# --- 4. Middleware + logging + observabilidad -----------------------------
run "feat(observabilidad): agrega CORS, timing middleware, logging y métricas (fase 9-10)" \
  "app/Middleware/ApiAuthMiddleware.php" "app/Middleware/CommercialStatusMiddleware.php" \
  "app/Middleware/CsrfMiddleware.php" "app/Middleware/FirmaMiddleware.php" \
  "app/Middleware/MiddlewareInterface.php" "app/Middleware/PermissionMiddleware.php" \
  "app/Middleware/PlanLimitMiddleware.php" "app/Middleware/PortalClienteMiddleware.php" \
  "app/Middleware/RateLimitMiddleware.php" "app/Middleware/CorsMiddleware.php" \
  "app/Middleware/RequestTimingMiddleware.php" "app/Logging" \
  "app/Monitoring/ErrorReporter.php" "app/Monitoring/MetricsCollector.php" \
  "database/migrations/0526_phase9_phase10_integraciones_observabilidad.sql" \
  "tests/Unit/Middleware/CorsMiddlewareTest.php"

# --- 5. Almacenamiento S3 --------------------------------------------------
run "feat(storage): integra almacenamiento S3 para documentos (fase 1)" \
  "app/Services/StorageService.php" \
  "database/migrations/0510_phase1_storage_s3.sql" \
  "scripts/migrate_documents_to_s3.php"

# --- 6. Cola de trabajos (jobs/queue) --------------------------------------
run "feat(jobs): implementa sistema de cola de trabajos en segundo plano (fase 1)" \
  "app/Jobs" "app/Queue" "app/Services/QueueService.php" \
  "app/Services/JobMonitorService.php" "app/Controllers/SuperadminJobController.php" \
  "app/Views/superadmin/jobs" "database/migrations/0511_phase1_job_queue.sql" \
  "scripts/queue_work.php" "tests/Unit/Services/QueueServiceTest.php" \
  "tests/Unit/Services/JobMonitorServiceTest.php"

# --- 7. Generación de PDF/DOCX ---------------------------------------------
run "feat(documentos): agrega generación de PDF/DOCX para honorarios (fase 1)" \
  "app/Services/PdfService.php" "app/Services/DocxService.php" "app/PdfTemplates" \
  "database/migrations/0512_phase1_honorarios_pdf.sql" \
  "tests/Unit/Services/PdfServiceTest.php" "tests/Unit/Services/DocxServiceTest.php"

# --- 8. Etapas y numeración de casos ----------------------------------------
run "feat(casos): agrega etapas de caso y numeración automática (fase 2)" \
  "app/Services/CasoEtapaService.php" \
  "database/migrations/0513_phase2_caso_etapas.sql" \
  "database/migrations/0514_phase2_caso_numero.sql" \
  "app/Controllers/CasoController.php" "app/Services/CasoService.php" \
  "app/Repositories/CasoRepository.php"

# --- 9. CRM: contactos y deduplicación --------------------------------------
run "feat(crm): agrega gestión de contactos con deduplicación (fase 2)" \
  "app/Services/ContactService.php" "app/Services/ContactDeduplicationService.php" \
  "database/migrations/0515_phase2_contacts.sql" \
  "database/migrations/0524_phase7_crm_engagement.sql" \
  "tests/Unit/Services/ContactDeduplicationServiceTest.php" \
  "tests/Unit/Services/CrmReportServiceTest.php"

# --- 10. Honorarios: líneas detalladas + API --------------------------------
run "feat(honorarios): agrega líneas de honorario detalladas y endpoint API (fase 2)" \
  "app/Services/HonorarioLineaService.php" \
  "app/Api/Controllers/V1/HonorarioApiController.php" \
  "app/Repositories/HonorarioRepository.php" "app/Services/HonorarioService.php" \
  "database/migrations/0516_phase2_honorario_lineas.sql" \
  "tests/Unit/HonorarioServiceTest.php"

# --- 11. Webhooks salientes --------------------------------------------------
run "feat(webhooks): implementa sistema de webhooks salientes (fase 2)" \
  "app/Services/WebhookService.php" "app/Api/Controllers/V1/WebhookApiController.php" \
  "database/migrations/0517_phase2_webhooks.sql" \
  "tests/Unit/Services/WebhookServiceTest.php"

# --- 12. Calendario jurídico + Google Calendar -------------------------------
run "feat(calendario): agrega calendario jurídico e integración con Google Calendar (fase 2)" \
  "app/Controllers/CalendarioController.php" "app/Controllers/GoogleCalendarController.php" \
  "app/Services/CalendarioEventoService.php" "app/Services/GoogleCalendarService.php" \
  "app/Views/calendario" "database/migrations/0508_calendario_permission.sql" \
  "database/migrations/0518_phase2_calendario_eventos.sql"

# --- 13. Cuentas fiduciarias (trust / IOLTA) ---------------------------------
run "feat(trust): implementa cuentas fiduciarias (IOLTA) y reportes (fase 3)" \
  "app/Services/TrustService.php" "app/Controllers/TrustController.php" \
  "database/migrations/0519_phase3_trust_accounts.sql" \
  "database/migrations/0520_phase3_trust_reportes.sql" \
  "database/migrations/0521_phase3_trust_permisos.sql" \
  "tests/Unit/Services/TrustServiceTest.php"

# --- 14. Facturación completa, Stripe y anticipos ----------------------------
run "feat(facturacion): agrega billing completo, pasarela Stripe y anticipos (fases 4-5)" \
  "app/Services/BillingService.php" "app/Services/StripeService.php" \
  "app/Services/RefundService.php" "app/Services/RetainerService.php" \
  "app/Controllers/PaymentController.php" "app/Views/payments" \
  "database/migrations/0522_phase4_billing_completo.sql" \
  "database/migrations/0523_phase5_stripe.sql" \
  "tests/Unit/Services/RetainerServiceTest.php" \
  "tests/Unit/Services/ReporteAgingServiceTest.php"

# --- 15. API pública v1 + rate limiting --------------------------------------
run "feat(api): expone API pública v1 con scopes y rate limiting" \
  "app/Api/Controllers/V1/DocumentoApiController.php" \
  "app/Api/Controllers/V1/PagoApiController.php" \
  "app/Api/Controllers/V1/TimeEntryApiController.php" \
  "app/Api/Controllers/V1/RequiresApiScope.php" \
  "app/Security/ApiRateLimitService.php" "app/Controllers/ApiDocsController.php" \
  "public/api" "routes/api_v1.php" "routes/api.php" \
  "tests/Unit/Api/RequiresApiScopeTest.php" \
  "tests/Unit/Security/ApiRateLimitServiceTest.php" \
  "app/Api/Controllers/ApiTokenController.php"

# --- 16. Superadmin: roles, usuarios y monitoreo -----------------------------
run "feat(superadmin): agrega gestión de roles/usuarios de plataforma y monitoreo" \
  "app/Controllers/SuperadminRolController.php" "app/Controllers/SuperadminUsuarioController.php" \
  "app/Services/SuperadminRolService.php" "app/Services/SuperadminUsuarioService.php" \
  "app/Repositories/SuperadminRolRepository.php" "app/Repositories/SuperadminUsuarioRepository.php" \
  "app/Views/superadmin/mis-roles" "app/Views/superadmin/mis-usuarios" \
  "app/Views/superadmin/monitoreo" "app/Views/superadmin/dashboard.php" \
  "app/Views/superadmin/firmas/show.php" "app/Views/superadmin/firmas/index.php" \
  "app/Views/superadmin/planes/index.php" \
  "database/migrations/0509_superadmin_roles.sql" "routes/superadmin.php"

# --- 17. Autenticación propia del portal de clientes -------------------------
run "feat(portal): agrega autenticación propia del portal de clientes" \
  "app/Controllers/PortalAuthController.php" "app/Services/PortalAuthService.php" \
  "app/Views/portal/activate.php" "app/Views/portal/login.php" "routes/portal.php"

# --- 18. Firma electrónica (DocuSign) ----------------------------------------
run "feat(firma-electronica): integra DocuSign para firma de documentos" \
  "app/Controllers/DocuSignController.php" "app/Services/DocuSignService.php"

# --- 19. GDPR ------------------------------------------------------------------
run "feat(gdpr): agrega servicio de cumplimiento GDPR (exportación/borrado de datos)" \
  "app/Services/GdprService.php" "database/migrations/0528_gdpr.sql" \
  "tests/Unit/Services/GdprServiceTest.php"

# --- 20. Sistema de diseño / UI --------------------------------------------
run "feat(ui): agrega sistema de diseño (sidebar, topbar, componentes UI)" \
  "public/assets/css/design-system.css" "public/assets/css/sidebar.css" \
  "public/assets/css/topbar.css" "public/assets/js/sidebar.js" \
  "public/assets/js/ui-components.js" "public/assets/css/app.css" \
  "public/assets/css/responsive.css" "public/assets/js/pwa-install.js" \
  "app/Views/layouts/app.php" "app/Views/layouts/public_form.php" \
  "app/Views/layouts/superadmin.php" "app/Views/partials/sidebar.php"

# --- 21. Refactor transversal de controladores/servicios existentes ----------
run "refactor: actualiza controladores, servicios y validadores existentes" \
  "app/Controllers/AceptacionLegalController.php" "app/Controllers/AuditoriaController.php" \
  "app/Controllers/AuthController.php" "app/Controllers/CasoComunicacionController.php" \
  "app/Controllers/ClienteController.php" "app/Controllers/FinanzasController.php" \
  "app/Controllers/FirmaController.php" "app/Controllers/HealthController.php" \
  "app/Controllers/PerfilController.php" "app/Controllers/PortalAutorizacionController.php" \
  "app/Controllers/ProspectoController.php" "app/Controllers/ReporteController.php" \
  "app/Controllers/RolController.php" "app/Controllers/SessionController.php" \
  "app/Repositories/AceptacionLegalRepository.php" "app/Repositories/BaseRepository.php" \
  "app/Repositories/CasoComunicacionRepository.php" "app/Repositories/DashboardRepository.php" \
  "app/Repositories/DocumentoRepository.php" "app/Repositories/DocumentoVersionRepository.php" \
  "app/Repositories/NotificacionRepository.php" "app/Repositories/PerfilRepository.php" \
  "app/Repositories/PortalAutorizacionRepository.php" "app/Repositories/ProspectoRepository.php" \
  "app/Repositories/ReporteRepository.php" "app/Repositories/RolRepository.php" \
  "app/Services/BookingService.php" "app/Services/CasoComunicacionService.php" \
  "app/Services/ClienteService.php" "app/Services/DashboardService.php" \
  "app/Services/DocumentTemplateService.php" "app/Services/DocumentoService.php" \
  "app/Services/DocumentoVersionService.php" "app/Services/ImportacionService.php" \
  "app/Services/IntakeFormService.php" "app/Services/LocalMailService.php" \
  "app/Services/MfaService.php" "app/Services/NotificacionService.php" \
  "app/Services/PagoService.php" "app/Services/PerfilService.php" \
  "app/Services/ProspectoService.php" "app/Services/ReporteService.php" \
  "app/Services/RolService.php" "app/Services/SensitiveDataService.php" \
  "app/Services/TemplateVariableService.php" "app/Services/UsuarioService.php" \
  "app/Validators/AceptacionLegalValidator.php" "app/Validators/CatalogoValidator.php" \
  "app/Validators/FirmaValidator.php" "app/Validators/LoginValidator.php" \
  "app/Validators/PlanValidator.php" "app/Validators/RolValidator.php" \
  "app/Views/booking/config.php" "app/Views/intake/form_builder.php" \
  "app/Views/perfil/show.php" "app/Views/public/booking.php" \
  "app/Views/public/intake_form.php" "app/Views/roles/index.php" \
  "config/mail.php" "config/permissions.php" "routes/web.php" \
  "database/migrations/0525_phase8_documentos_completos.sql"

# --- 22. Pruebas unitarias adicionales ---------------------------------------
run "test: agrega pruebas unitarias faltantes para RBAC, cifrado y plantillas" \
  "tests/Unit/RbacTest.php" "tests/Unit/Services/ChangePasswordServiceTest.php" \
  "tests/Unit/Services/DocumentoVersionMagicBytesTest.php" \
  "tests/Unit/Services/SensitiveDataEncryptionTest.php" \
  "tests/Unit/Services/DocumentTemplateServiceTest.php" \
  "tests/Unit/Services/TemplateVariableServiceTest.php" "tests/Integration"

# --- 23. Documentación --------------------------------------------------------
run "docs: actualiza documento de fases de implementación" \
  "docs/IMPLEMENTACION_FASES.md"

echo ""
echo "==================================================================="
echo " Listo. Revisa el resultado con:"
echo "   git log --oneline -30"
echo "   git status"
echo ""
echo " NOTA: .phpunit.result.cache quedó fuera intencionalmente (es un"
echo " archivo de caché de PHPUnit, no debería versionarse; si quieres,"
echo " puedes hacer luego: git rm --cached .phpunit.result.cache"
echo " y agregarlo a .gitignore)."
echo "==================================================================="
