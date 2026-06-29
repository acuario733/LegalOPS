<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class CommercialStatusMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Auth $auth,
        private readonly \App\Services\CommercialStatusService $commercialStatus
    )
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $status = $this->auth->user()['firma_estado'] ?? null;
        if (!$this->commercialStatus->allowsOperation($status)) {
            throw new HttpException(423, $this->commercialStatus->consequence($status), ['code' => 'COMMERCIAL_STATUS_BLOCKED']);
        }

        return $next($request);
    }
}
