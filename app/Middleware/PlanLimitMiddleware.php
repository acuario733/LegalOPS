<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class PlanLimitMiddleware implements MiddlewareInterface
{
    private Closure $checker;

    public function __construct(callable $checker, private readonly string $resource)
    {
        $this->checker = Closure::fromCallable($checker);
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!(bool) ($this->checker)($this->resource, $request)) {
            throw new HttpException(409, 'El límite del plan no permite crear más recursos.', ['code' => 'PLAN_LIMIT_REACHED']);
        }

        return $next($request);
    }
}
