<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

final class AuthMiddleware implements MiddlewareInterface
{
    private ?Closure $sessionVerifier;

    public function __construct(private readonly Auth $auth, ?callable $sessionVerifier = null)
    {
        $this->sessionVerifier = $sessionVerifier === null ? null : Closure::fromCallable($sessionVerifier);
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            return $request->wantsJson()
                ? Response::error('Debe iniciar sesión para continuar.', 401)
                : Response::redirect('/login');
        }

        if ($this->sessionVerifier !== null && !(bool) ($this->sessionVerifier)()) {
            $this->auth->logout();

            return $request->wantsJson()
                ? Response::error('La sesión fue revocada o expiró.', 401)
                : Response::redirect('/login');
        }

        return $next($request);
    }
}
