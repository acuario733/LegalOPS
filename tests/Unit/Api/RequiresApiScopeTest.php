<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Api\Controllers\V1\RequiresApiScope;
use App\Core\HttpException;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequiresApiScopeTest extends TestCase
{
    public function testEmptyScopesAreDenied(): void
    {
        $request = new Request('GET', '/api/v1/honorarios');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Scope insuficiente');

        $this->checker()->check($request, 'billing:read');
    }

    public function testExactScopeIsAllowed(): void
    {
        $request = new Request('GET', '/api/v1/honorarios');
        $request->setAttribute('api_scopes', ['billing:read']);

        $this->checker()->check($request, 'billing:read');

        self::assertTrue(true);
    }

    private function checker(): object
    {
        return new class {
            use RequiresApiScope;

            public function check(Request $request, string $scope): void
            {
                $this->requireApiScope($request, $scope);
            }
        };
    }
}
