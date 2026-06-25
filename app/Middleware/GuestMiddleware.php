<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            return $next($request);
        }
        $destination = match ($this->auth->user()['tipo'] ?? null) {
            'superadmin' => '/superadmin/firmas',
            'cliente_externo' => '/portal',
            default => '/health',
        };

        return Response::redirect($destination);
    }
}
