<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Permission $permissions,
        private readonly Auth $auth,
        private readonly string $permission
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->permissions->allows($this->permission, $this->auth->user())) {
            throw new HttpException(403, 'No tiene permiso para realizar esta acción.');
        }

        return $next($request);
    }
}

