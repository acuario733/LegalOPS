<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private Closure $checker;

    public function __construct(callable $checker)
    {
        $this->checker = Closure::fromCallable($checker);
    }

    public function handle(Request $request, callable $next): Response
    {
        $result = ($this->checker)($request);
        if ($result === false || (is_array($result) && !($result['allowed'] ?? false))) {
            $retryAfter = is_array($result) ? max(1, (int) ($result['retry_after'] ?? 60)) : 60;

            return Response::error('Demasiadas solicitudes. Intente nuevamente más tarde.', 429)
                ->withHeader('Retry-After', (string) $retryAfter);
        }

        return $next($request);
    }
}
