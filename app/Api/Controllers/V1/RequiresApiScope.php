<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\HttpException;
use App\Core\Request;

trait RequiresApiScope
{
    private function requireApiScope(Request $request, string $scope): void
    {
        $scopes = (array) $request->getAttribute('api_scopes', []);
        if (!in_array($scope, $scopes, true)) {
            throw new HttpException(403, 'Scope insuficiente');
        }
    }

    private function apiFirmaId(Request $request): int
    {
        $firmaId = (int) $request->getAttribute('api_firma_id', 0);
        if ($firmaId <= 0) {
            throw new HttpException(401, 'Contexto de API invalido.');
        }

        return $firmaId;
    }
}
