<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Logging\StructuredLogger;
use Throwable;

final class RequestTimingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly StructuredLogger $logger,
        private readonly Auth $auth
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $startedAt = hrtime(true);
        $correlationId = $this->correlationId($request);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->status();

            return $response->withHeader('X-Correlation-ID', $correlationId);
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpException ? $exception->status() : 500;
            throw $exception;
        } finally {
            $this->logger->log($status >= 500 ? 'error' : 'info', 'http_request', [
                'correlation_id' => $correlationId,
                'firma_id' => $request->getAttribute('api_firma_id', $this->auth->firmaId()),
                'usuario_id' => $request->getAttribute('api_usuario_id', $this->auth->id()),
                'method' => $request->method(),
                'path' => $request->uri(),
                'status' => $status,
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            ]);
        }
    }

    private function correlationId(Request $request): string
    {
        $provided = trim((string) $request->header('x-correlation-id', ''));

        return preg_match('/^[A-Za-z0-9._-]{8,64}$/', $provided) === 1 ? $provided : bin2hex(random_bytes(8));
    }
}
