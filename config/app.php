<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'name' => Config::env('APP_NAME', 'LegalOPS Cloud'),
    'environment' => Config::env('APP_ENV', 'production'),
    'debug' => Config::env('APP_DEBUG', false),
    'url' => rtrim((string) Config::env('APP_URL', ''), '/'),
    'timezone' => Config::env('APP_TIMEZONE', 'UTC'),
    'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
    'audit_log_path' => dirname(__DIR__) . '/storage/logs/audit.log',
    'log_retention_days' => (int) Config::env('LOG_RETENTION_DAYS', 30),
    'backup_path' => dirname(__DIR__) . '/storage/backups',
    'backup_retention_days' => (int) Config::env('BACKUP_RETENTION_DAYS', 14),
    'inbound_email_domain' => Config::env('INBOUND_EMAIL_DOMAIN', 'inbound.legal.com'),
    'inbound_email_secret' => Config::env('INBOUND_EMAIL_WEBHOOK_SECRET', ''),
];
