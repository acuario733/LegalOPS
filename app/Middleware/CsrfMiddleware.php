<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (str_starts_with($request->uri(), '/webhooks/') || str_starts_with($request->uri(), '/api/v1/')) {
            return $next($request);
        }

        if (!$request->isSafeMethod() && !$this->csrf->validate($this->csrf->tokenFromRequest($request))) {
            throw new HttpException(419, 'La sesion del formulario expiro. Recargue la pagina e intente nuevamente.');
        }

        return $next($request);
    }
}
