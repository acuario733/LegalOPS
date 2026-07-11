<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\MfaService;

/**
 * Bloquea acceso a rutas protegidas hasta que el usuario complete MFA.
 *
 * El flag mfa_verified se establece en sesion cuando el usuario supera
 * el challenge en /mfa/verify. Si MFA esta habilitado para el usuario
 * pero no ha verificado en esta sesion, redirige a /mfa/verify.
 */
final class EnsureMfaVerified implements MiddlewareInterface
{
    public function __construct(
        private readonly Auth $auth,
        private readonly MfaService $mfa
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();
        if ($user === null) {
            return Response::redirect('/login');
        }

        // Si ya verifico MFA en esta sesion, continua
        if (!empty($user['mfa_verified'])) {
            return $next($request);
        }

        $firmaId   = isset($user['firma_id']) ? (int) $user['firma_id'] : null;
        $usuarioId = (int) $user['id'];
        $tipo      = (string) ($user['tipo'] ?? '');

        // Solo aplica si MFA esta habilitado para el rol y para el usuario especifico
        if ($firmaId === null || !$this->mfa->isMfaRequerido($tipo)) {
            return $next($request);
        }

        if (!$this->mfa->isMfaHabilitado($firmaId, $usuarioId)) {
            return $next($request);
        }

        // MFA requerido y no verificado → challenge
        return $request->wantsJson()
            ? Response::error('Se requiere verificacion MFA para continuar.', 403)
            : Response::redirect('/mfa/verify');
    }
}
