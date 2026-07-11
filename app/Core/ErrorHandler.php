<?php

declare(strict_types=1);

namespace App\Core;

use App\Monitoring\ErrorReporter;
use ErrorException;
use PDOException;
use Throwable;

final class ErrorHandler
{
    public function __construct(
        private readonly string $logPath,
        private readonly ?ErrorReporter $reporter = null,
    ) {
    }


    public function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $exception): void {
            $this->handle($exception, Request::capture())->send();
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            if (!headers_sent()) {
                $this->handle($exception, Request::capture())->send();
            }
        });
    }

    public function handle(Throwable $exception, Request $request): Response
    {
        $correlationId = bin2hex(random_bytes(8));
        $this->writeLog($exception, $request, $correlationId);
        $this->reporter?->report($exception, $correlationId, $request->uri());

        $status = $exception instanceof HttpException ? $exception->status() : 500;
        $message = $exception instanceof HttpException
            ? $exception->getMessage()
            : 'Ocurrió un error inesperado. Intente nuevamente o informe el código de referencia.';
        $errors = $exception instanceof HttpException ? $exception->errors() : [];

        if ($request->wantsJson()) {
            return Response::json(
                ['correlation_id' => $correlationId],
                $message,
                $status,
                $errors,
                false,
                ['X-Correlation-ID' => $correlationId]
            );
        }

        $safeMessage = e($message);
        $safeCorrelationId = e($correlationId);
        $html = <<<HTML
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error {$status}</title></head>
<body><main><h1>Error {$status}</h1><p>{$safeMessage}</p><p>Referencia: <code>{$safeCorrelationId}</code></p></main></body>
</html>
HTML;

        return Response::html($html, $status, ['X-Correlation-ID' => $correlationId]);
    }

    private function writeLog(Throwable $exception, Request $request, string $correlationId): void
    {
        $directory = dirname($this->logPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }

        $message = $exception instanceof PDOException
            ? 'Database exception'
            : $this->sanitize($exception->getMessage());

        $record = [
            'ts' => gmdate('c'),
            'timestamp' => gmdate('c'),
            'level' => 'ERROR',
            'cid' => $correlationId,
            'firm' => $request->getAttribute('api_firma_id'),
            'uid' => $request->getAttribute('api_usuario_id'),
            'exception' => $exception::class,
            'message' => $message,
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'method' => $request->method(),
            'path' => $request->uri(),
            'status' => $exception instanceof HttpException ? $exception->status() : 500,
            'ms' => null,
            'ip' => $request->ip(),
        ];

        $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded)) {
            file_put_contents($this->logPath, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    private function sanitize(string $message): string
    {
        $patterns = [
            '/(password|passwd|secret|token|authorization|cookie)\s*[=:]\s*[^\s,;]+/i',
            '/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i',
        ];

        return preg_replace($patterns, '$1=[REDACTED]', $message) ?? 'Error interno';
    }
}
