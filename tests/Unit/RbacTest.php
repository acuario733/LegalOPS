<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Permission;
use PHPUnit\Framework\TestCase;

final class RbacTest extends TestCase
{
    public function testParalegalNoPuedeAccederATrustVer(): void
    {
        $permission = new Permission();
        $paralegal = [
            'id' => 9,
            'firma_id' => 1,
            'roles' => [[
                'codigo' => 'paralegal',
                'permissions' => ['casos.ver', 'documentos.ver', 'tareas.ver'],
            ]],
        ];

        self::assertFalse($permission->allows('trust.ver', $paralegal));
    }
}
