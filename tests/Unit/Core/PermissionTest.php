<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Permission;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function test_resolver_permissions_are_used_for_existing_session(): void
    {
        $permission = new Permission(static fn (array $user): array => ['intake.ver', 'booking.ver']);

        self::assertTrue($permission->allows('intake.ver', [
            'id' => 10,
            'firma_id' => 20,
            'permissions' => [],
        ]));
        self::assertTrue($permission->allows('booking.ver', [
            'id' => 10,
            'firma_id' => 20,
            'permissions' => [],
        ]));
    }

    public function test_session_permissions_still_work_without_resolver(): void
    {
        $permission = new Permission();

        self::assertTrue($permission->allows('clientes.ver', [
            'id' => 10,
            'firma_id' => 20,
            'permissions' => ['clientes.ver'],
        ]));
    }
}
