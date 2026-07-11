<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\ApiRateLimitService;
use PHPUnit\Framework\TestCase;

final class ApiRateLimitServiceTest extends TestCase
{
    public function testSlidingWindowBlocksAfterConfiguredLimit(): void
    {
        $service = new ApiRateLimitService(null, 2);
        $tokenId = random_int(100000, 999999);

        self::assertTrue($service->check($tokenId, 10000)['allowed']);
        self::assertTrue($service->check($tokenId, 10001)['allowed']);
        $blocked = $service->check($tokenId, 10002);

        self::assertFalse($blocked['allowed']);
        self::assertSame(0, $blocked['remaining']);
        self::assertSame(3598, $blocked['retry_after']);
    }

    public function testSlidingWindowExpiresOldRequests(): void
    {
        $service = new ApiRateLimitService(null, 1);
        $tokenId = random_int(1000000, 1999999);

        self::assertTrue($service->check($tokenId, 10000)['allowed']);
        self::assertTrue($service->check($tokenId, 13601)['allowed']);
    }
}
