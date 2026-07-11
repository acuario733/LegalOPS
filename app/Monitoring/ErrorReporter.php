<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Core\HttpException;
use Throwable;

/**
 * ErrorReporter — envía errores críticos a canales externos (Sentry, Slack).
 *
 * Diseñado para ser llamado desde ErrorHandler::handle() después de escribir el log local.
 *
 * Configuración via .env:
 *   SENTRY_DSN=https://...@sentry.io/...   (vacío = desactivado)
 *   SLACK_WEBHOOK_URL=https://hooks.slack.com/...  (vacío = desactivado)
 *   APP_ENV=production  (solo reporta en producción por defecto)
 *
 * El reporte es FIRE-AND-FORGET: un fallo al notificar no interrumpe la response.
 * Errores de red/Sentry se escriben al error_log del sistema, no al usuario.
 */
final class ErrorReporter
{
    private const IGNORED_STATUS_CODES = [400, 401, 403, 404, 405, 422, 429];

    public function __construct(
        private readonly string $sentryDsn = '',
        private readonly string $slackWebhookUrl = '',
        private readonly string $appEnv = 'production',
        private readonly string $appUrl = '',
    ) {
    }

    /**
     * Reporta un error si es elegible (no es HTTP client error, estamos en producción).
     */
    public function report(Throwable $exception, string $correlationId, string $requestUri = ''): void
    {
        // No reportar errores de cliente (4xx)
        if (
            $exception instanceof HttpException
            && in_array($exception->status(), self::IGNORED_STATUS_CODES, true)
        ) {
            return;
        }

        // Solo reportar en producción (o si se fuerza via TEST_REPORTING=true)
        if ($this->appEnv !== 'production' && getenv('TEST_REPORTING') !== 'true') {
            return;
        }

        $this->notifySentry($exception, $correlationId);
        $this->notifySlack($exception, $correlationId, $requestUri);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sentry
    // ─────────────────────────────────────────────────────────────────────────

    private function notifySentry(Throwable $exception, string $correlationId): void
    {
        if ($this->sentryDsn === '') {
            return;
        }

        try {
            $parsed = $this->parseDsn($this->sentryDsn);
            if ($parsed === null) {
                return;
            }

            $payload = json_encode([
                'event_id'   => str_replace('-', '', $correlationId) . str_repeat('0', 16),
                'timestamp'  => gmdate('Y-m-d\TH:i:s'),
                'platform'   => 'php',
                'level'      => 'error',
                'exception'  => [
                    'values' => [[
                        'type'  => $exception::class,
                        'value' => $this->sanitizeMessage($exception->getMessage()),
                    ]],
                ],
                'tags' => ['correlation_id' => $correlationId],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $this->httpPost(
                url: "https://{$parsed['host']}/api/{$parsed['project']}/store/",
                body: $payload,
                headers: [
                    'Content-Type: application/json',
                    "X-Sentry-Auth: Sentry sentry_version=7, sentry_key={$parsed['key']}, sentry_client=legalops/1.0",
                ],
            );
        } catch (\Throwable $e) {
            error_log('[ErrorReporter] Sentry notify failed: ' . $e->getMessage());
        }
    }

    /** @return array{host: string, key: string, project: string}|null */
    private function parseDsn(string $dsn): ?array
    {
        $parts = parse_url($dsn);
        if (!is_array($parts)) {
            return null;
        }

        $key     = $parts['user'] ?? '';
        $host    = $parts['host'] ?? '';
        $project = ltrim((string) ($parts['path'] ?? ''), '/');

        if ($key === '' || $host === '' || $project === '') {
            return null;
        }

        return ['host' => $host, 'key' => $key, 'project' => $project];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Slack
    // ─────────────────────────────────────────────────────────────────────────

    private function notifySlack(Throwable $exception, string $correlationId, string $requestUri): void
    {
        if ($this->slackWebhookUrl === '') {
            return;
        }

        try {
            $message    = $this->sanitizeMessage($exception->getMessage());
            $exClass    = $exception::class;
            $file       = basename($exception->getFile()) . ':' . $exception->getLine();
            $url        = rtrim($this->appUrl, '/') . $requestUri;

            $payload = json_encode([
                'text'        => ":rotating_light: *Error en LegalOPS Cloud*",
                'attachments' => [[
                    'color'  => '#FF0000',
                    'fields' => [
                        ['title' => 'Excepción',       'value' => $exClass,       'short' => true],
                        ['title' => 'Correlation ID',  'value' => $correlationId, 'short' => true],
                        ['title' => 'Mensaje',         'value' => $message,       'short' => false],
                        ['title' => 'Archivo',         'value' => $file,          'short' => true],
                        ['title' => 'URL',             'value' => $url,           'short' => false],
                        ['title' => 'Timestamp',       'value' => gmdate('c'),    'short' => true],
                    ],
                ]],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $this->httpPost(
                url: $this->slackWebhookUrl,
                body: $payload,
                headers: ['Content-Type: application/json'],
            );
        } catch (\Throwable $e) {
            error_log('[ErrorReporter] Slack notify failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST via cURL con timeout corto (2s) para no bloquear la response al usuario.
     *
     * @param string[] $headers
     */
    private function httpPost(string $url, string $body, array $headers): void
    {
        if (!function_exists('curl_init')) {
            // Si no hay cURL, intentar con file_get_contents
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => implode("\r\n", $headers),
                    'content' => $body,
                    'timeout' => 2,
                    'ignore_errors' => true,
                ],
            ]);
            @file_get_contents($url, false, $context);
            return;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,          // Máx 2s — no bloquear la app
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function sanitizeMessage(string $message): string
    {
        return (string) preg_replace(
            '/(password|passwd|secret|token|authorization|cookie)\s*[=:]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message
        );
    }
}
