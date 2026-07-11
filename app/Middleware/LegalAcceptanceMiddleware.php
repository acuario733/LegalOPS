<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AceptacionLegalService;

final class LegalAcceptanceMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AceptacionLegalService $legal)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isAllowedWhilePending($request) || $this->legal->pending() === []) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::error('Debe aceptar los documentos legales pendientes antes de continuar.', 428, [
                'code' => 'LEGAL_ACCEPTANCE_REQUIRED',
            ]);
        }

        return Response::redirect(str_starts_with($request->uri(), '/portal') ? '/portal' : '/legal/pendientes');
    }

    private function isAllowedWhilePending(Request $request): bool
    {
        $uri = $request->uri();
        if ($uri === '/logout' || $uri === '/legal/pendientes' || preg_match('#^/legal/\d+/aceptar$#', $uri) === 1) {
            return true;
        }

        return $uri === '/portal' || preg_match('#^/portal/legal/\d+/aceptar$#', $uri) === 1;
    }
}
