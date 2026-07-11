<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SensitiveDataService;
use PHPUnit\Framework\TestCase;

final class SensitiveDataEncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENCRYPTION_KEY=test-only-key-with-enough-entropy');
    }

    protected function tearDown(): void
    {
        putenv('APP_ENCRYPTION_KEY');
    }

    public function testEncryptsWithRandomNonceAndDecrypts(): void
    {
        $service = new SensitiveDataService();
        $first = $service->encrypt('secret-token');
        $second = $service->encrypt('secret-token');

        self::assertNotSame($first, $second);
        self::assertSame('secret-token', $service->decrypt($first));
        self::assertSame('secret-token', $service->decrypt($second));
    }
}
