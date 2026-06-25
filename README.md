# LegalOPS Cloud

ERP juridico SaaS multiempresa desarrollado con PHP 8.2+, MySQL 8, PDO, AdminLTE 4 y Fetch API, sin framework PHP externo.

## Estado actual

El nucleo tecnico, la fase SaaS, la operacion juridica, documentos, finanzas y la fase 05 comercial/portal estan implementados. La aplicacion incluye aislamiento por firma, planes y limites, usuarios, RBAC, autenticacion persistida, recuperacion de contrasena, sesiones revocables, auditoria operativa, catalogos configurables, aceptacion versionada de documentos legales, notificaciones, dashboard, portal cliente, reportes, importaciones, soporte, onboarding y checklist owner.

## Organizacion documental y tecnica

- `01_DOCS/`, ubicada fuera de esta raiz, conserva los documentos canonicos y la trazabilidad del proyecto.
- `02_CODIGO_FUENTE/legalops/` contiene exclusivamente la aplicacion.
- `public/` es la unica raiz que puede exponerse mediante Apache o Nginx.
- `app/`, `config/`, `database/`, `routes/`, `scripts/` y `storage/` deben permanecer fuera del acceso web.

## Instalacion local

1. Use PHP 8.2 o superior y Composer 2.
2. Genere el autoload:

```bash
composer install --no-dev
```

3. Copie `.env.example` como `.env` y complete las credenciales locales. Nunca versione `.env`.
4. Cree la base de datos MySQL 8 con charset `utf8mb4`.
5. Consulte y aplique migraciones:

```bash
php scripts/migrate.php status
php scripts/migrate.php up
```

6. Cree el primer superadministrador desde CLI:

```bash
php scripts/create_superadmin.php correo@dominio.com "contrasena-temporal-segura" "Nombre completo"
```

La contrasena debe tener al menos 12 caracteres. Ejecute el comando en una consola controlada y evite conservar credenciales reales en el historial del shell.

7. Configure el servidor web para que su raiz sea `public/`. Para una revision local puntual:

```bash
php -S 127.0.0.1:8080 -t public
```

Rutas tecnicas disponibles:

- `GET /health`: estado HTML mediante layout AdminLTE.
- `GET /api/health`: respuesta JSON uniforme.
- `POST /api/health`: verificacion del middleware CSRF.
- `/health/error` y `/api/health/error`: solo existen en `APP_ENV=development` para verificar errores seguros.

Rutas funcionales principales:

- `/login`, `/forgot-password` y `/reset-password`: autenticacion y recuperacion.
- `/superadmin/firmas` y `/superadmin/planes`: administracion global SaaS.
- `/superadmin/soporte` y `/superadmin/checklist`: soporte global y aprobacion owner.
- `/usuarios`, `/roles`, `/sesiones`, `/auditoria` y `/catalogos`: gestion de cada firma.
- `/dashboard`, `/notificaciones`, `/portal-autorizaciones`, `/reportes`, `/importaciones`, `/soporte` y `/onboarding`: operacion comercial de fase 05.
- `/portal`: portal autenticado para clientes externos con casos, documentos, finanzas y aceptaciones legales.
- `/legal/pendientes`: aceptacion interna de la version legal vigente.

En desarrollo, los mensajes de recuperacion se depositan fuera de la raiz publica en `storage/temp/mail/`. Antes de produccion se debe configurar y aprobar un transporte de correo real.

## Assets de interfaz

Los archivos de distribucion se conservan localmente, junto con sus licencias:

- AdminLTE `4.0.2`.
- Bootstrap `5.3.8`.
- Bootstrap Icons `1.13.1`.

## Directorios de escritura

El proceso PHP y los procesos CLI solo deben recibir permisos de escritura en:

- `storage/documents/firmas/`
- `storage/exports/`
- `storage/imports/`
- `storage/logs/`
- `storage/temp/`
- `storage/backups/`

Se debe aplicar el principio de privilegio minimo: propietario y grupo del servicio, sin permisos globales de escritura. Los archivos generados en estos directorios estan excluidos del control de versiones.

## Produccion

- Use `APP_ENV=production`, `APP_DEBUG=false` y `SESSION_SECURE=true`.
- En produccion la aplicacion fuerza cookie segura aunque `SESSION_SECURE` quede mal configurado.
- Publique exclusivamente `public/`.
- No muestre errores PHP en pantalla.
- Mantenga credenciales, SQL, logs, documentos y backups fuera de `public/`.
- Ejecute `composer install --no-dev --optimize-autoloader` durante el despliegue.
- Configure `LOG_RETENTION_DAYS`, `BACKUP_RETENTION_DAYS` y `MYSQLDUMP_BIN` segun la infraestructura aprobada.
- RPO, RTO, frecuencia final de backup, responsables y retencion legal requieren aprobacion del propietario antes de produccion.

## Verificacion basica

```bash
composer validate --strict
composer dump-autoload --optimize
php -l public/index.php
php scripts/migrate.php status
```

Procesos programados recomendados para operacion:

```bash
php scripts/cron_alertas.php
php scripts/cron_sesiones.php
php scripts/cron_limpieza.php
php scripts/cron_backups.php
```

`cron_alertas.php` genera notificaciones de terminos, audiencias y tareas. `cron_sesiones.php` revoca sesiones expiradas. `cron_limpieza.php` revoca sesiones expiradas, elimina exportaciones/importaciones temporales vencidas y purga logs vencidos. `cron_backups.php` genera un respaldo SQL en `storage/backups/`; si `mysqldump` no esta en PATH, configure `MYSQLDUMP_BIN`.

Cada proceso usa lock en `storage/locks/`, escribe log JSON en `storage/logs/` y devuelve codigo distinto de cero ante fallo.

Ejemplo Linux cron:

```cron
*/15 * * * * cd /ruta/legalops && php scripts/cron_alertas.php
*/30 * * * * cd /ruta/legalops && php scripts/cron_sesiones.php
0 2 * * * cd /ruta/legalops && php scripts/cron_limpieza.php
0 3 * * * cd /ruta/legalops && php scripts/cron_backups.php
```

Ejemplo Windows Task Scheduler:

```powershell
schtasks /Create /SC MINUTE /MO 15 /TN "LegalOPS Alertas" /TR "php C:\ruta\legalops\scripts\cron_alertas.php"
schtasks /Create /SC DAILY /ST 02:00 /TN "LegalOPS Limpieza" /TR "php C:\ruta\legalops\scripts\cron_limpieza.php"
schtasks /Create /SC DAILY /ST 03:00 /TN "LegalOPS Backups" /TR "php C:\ruta\legalops\scripts\cron_backups.php"
```

El procedimiento de recuperacion esta documentado en `database/docs/restore-procedure.md`.
