<?php

declare(strict_types=1);

/**
 * Configuración de monitoreo de errores en producción.
 *
 * Variables de entorno:
 *   SENTRY_DSN             — DSN de Sentry (vacío = desactivado)
 *   SLACK_WEBHOOK_URL      — URL del webhook de Slack (vacío = desactivado)
 *
 * Para activar en producción, definir en .env:
 *   SENTRY_DSN=https://<key>@sentry.io/<project>
 *   SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T.../B.../...
 */
return [
    'sentry_dsn'         => (string) getenv('SENTRY_DSN'),
    'slack_webhook_url'  => (string) getenv('SLACK_WEBHOOK_URL'),
];
