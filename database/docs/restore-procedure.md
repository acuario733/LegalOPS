# Procedimiento de recuperacion

Este procedimiento restaura una copia SQL generada por `scripts/cron_backups.php`.

## Generar backup

```bash
php scripts/cron_backups.php
```

El archivo queda en `storage/backups/` con nombre `legalops_YYYYMMDD_HHMMSS.sql`. La retencion se controla con `BACKUP_RETENTION_DAYS`.

Si `mysqldump` no esta en `PATH`, configure `MYSQLDUMP_BIN` con la ruta absoluta aprobada.

## Restaurar en ambiente controlado

1. Detenga procesos web y cron para evitar escrituras concurrentes.
2. Cree una base de datos vacia con `utf8mb4`.
3. Restaure el SQL:

```bash
mysql --host=127.0.0.1 --port=3306 --user=root --default-character-set=utf8mb4 legalops < storage/backups/legalops_YYYYMMDD_HHMMSS.sql
```

4. Ejecute verificacion basica:

```bash
php scripts/migrate.php status
composer validate --strict
php scripts/cron_limpieza.php
```

5. Inicie la aplicacion y confirme ingreso de superadmin, administrador de firma y portal cliente.

## Frecuencia y aprobacion

Durante desarrollo el backup puede ejecutarse manualmente o por tarea programada diaria. Antes de produccion deben quedar aprobados RPO, RTO, frecuencia, retencion, responsable operativo y responsable de aprobacion del simulacro.

## Evidencia minima

- Nombre del backup restaurado.
- Fecha y hora de inicio y cierre.
- Resultado de `php scripts/migrate.php status`.
- Resultado de `GET /api/health`.
- Usuario responsable de aprobar la recuperacion.
