<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class CommercialStatusMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $status = strtolower((string) ($this->auth->user()['firma_estado'] ?? 'active'));
        if (!in_array($status, ['active', 'activa'], true)) {
            throw new HttpException(423, 'La firma no está habilitada para operar.');
        }

        return $next($request);
    }
}

