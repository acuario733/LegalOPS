<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class SuperadminMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (($this->auth->user()['tipo'] ?? null) !== 'superadmin') {
            throw new HttpException(403, 'El recurso requiere acceso de superadministración.');
        }

        return $next($request);
    }
}
