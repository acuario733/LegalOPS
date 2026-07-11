<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\RedisRateLimiter;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;
use Predis\Connection\ConnectionException;

/**
 * Tests para RedisRateLimiter.
 *
 * Usa un mock de Predis\Client para no requerir Redis en CI.
 */
class RedisRateLimiterTest extends TestCase
{
    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Crea un mock de RedisClient con INCR retornando $incrReturn
     * y EXPIRE + TTL con valores predefinidos.
     */
    private function makeRedis(
        int $incrReturn = 1,
        int $ttlReturn = 55,
        bool $throwConnection = false,
    ): RedisClient {
        $redis = $this->createMock(RedisClient::class);

        if ($throwConnection) {
            $redis->method('incr')
                ->willThrowException(new ConnectionException(
                    $this->createMock(\Predis\Connection\NodeConnectionInterface::class),
                    'Connection refused',
                ));

            return $redis;
        }

        $redis->method('incr')->willReturn($incrReturn);
        $redis->method('expire')->willReturn(1);
        $redis->method('get')->willReturn($incrReturn > 0 ? (string) $incrReturn : null);
        $redis->method('ttl')->willReturn($ttlReturn);
        $redis->method('del')->willReturn(1);

        return $redis;
    }

    // ────────────────────────────────────────────────────────────────
    // Tests
    // ────────────────────────────────────────────────────────────────

    public function test_allow_returns_true_on_first_attempt_with_redis(): void
    {
        $redis = $this->makeRedis(incrReturn: 1);
        $limiter = new RedisRateLimiter($redis, defaultLimit: 60, defaultWindowSeconds: 60);

        $result = $limiter->allow('192.168.1.1', 'POST /login');

        $this->assertTrue($result);
    }

    public function test_allow_returns_false_when_redis_limit_exceeded(): void
    {
        // INCR retorna 61 → ya supera el límite de 60
        $redis = $this->makeRedis(incrReturn: 61);
        $limiter = new RedisRateLimiter($redis, defaultLimit: 60, defaultWindowSeconds: 60);

        $result = $limiter->allow('192.168.1.1', 'POST /login');

        $this->assertFalse($result);
    }

    public function test_different_keys_are_isolated_in_redis(): void
    {
        // Verificamos que se construyen claves distintas para distintos endpoints
        $redis = $this->getMockBuilder(RedisClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $calledKeys = [];
        $redis->method('incr')
            ->willReturnCallback(function (string $key) use (&$calledKeys): int {
                $calledKeys[] = $key;

                return 1;
            });
        $redis->method('expire')->willReturn(1);

        $limiter = new RedisRateLimiter($redis);

        $limiter->allow('ip_A', 'POST /login');
        $limiter->allow('ip_B', 'POST /login');
        $limiter->allow('ip_A', 'POST /clientes');

        $this->assertCount(3, array_unique($calledKeys));
        $this->assertContains('rate_limit:ip_A:POST /login', $calledKeys);
        $this->assertContains('rate_limit:ip_B:POST /login', $calledKeys);
        $this->assertContains('rate_limit:ip_A:POST /clientes', $calledKeys);
    }

    public function test_reset_deletes_redis_key(): void
    {
        $redis = $this->getMockBuilder(RedisClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $deletedKeys = [];
        $redis->method('del')
            ->willReturnCallback(function (array $keys) use (&$deletedKeys): int {
                $deletedKeys = array_merge($deletedKeys, $keys);

                return count($keys);
            });

        $limiter = new RedisRateLimiter($redis);
        $limiter->reset('192.168.1.1', 'POST /login');

        $this->assertContains('rate_limit:192.168.1.1:POST /login', $deletedKeys);
    }

    public function test_get_attempts_reads_from_redis(): void
    {
        $redis = $this->makeRedis(incrReturn: 42);
        $limiter = new RedisRateLimiter($redis);

        $attempts = $limiter->getAttempts('192.168.1.1', 'POST /login');

        $this->assertSame(42, $attempts);
    }

    public function test_get_retry_after_returns_remaining_ttl(): void
    {
        $redis = $this->makeRedis(ttlReturn: 37);
        $limiter = new RedisRateLimiter($redis);

        $retryAfter = $limiter->getRetryAfter('192.168.1.1', 'POST /login');

        $this->assertSame(37, $retryAfter);
    }

    public function test_fallback_allows_request_when_redis_unavailable(): void
    {
        $redis = $this->makeRedis(throwConnection: true);
        $limiter = new RedisRateLimiter($redis, defaultLimit: 3);

        // Aunque estamos "por encima del límite" (Redis no responde), debe permitir
        $result = $limiter->allow('192.168.1.1', 'POST /login');

        $this->assertTrue($result, 'Fail-open: debe permitir cuando Redis no está disponible');
    }

    public function test_redis_key_expires_after_decay_window(): void
    {
        $redis = $this->getMockBuilder(RedisClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $expireCallArgs = [];
        $redis->method('incr')->willReturn(1); // primer intento → asigna TTL
        $redis->method('expire')
            ->willReturnCallback(function (string $key, int $seconds) use (&$expireCallArgs): int {
                $expireCallArgs[] = ['key' => $key, 'seconds' => $seconds];

                return 1;
            });

        $limiter = new RedisRateLimiter($redis, defaultLimit: 60, defaultWindowSeconds: 90);
        $limiter->allow('192.168.1.1', 'POST /clientes', windowSeconds: 90);

        $this->assertCount(1, $expireCallArgs, 'EXPIRE debe llamarse exactamente una vez por primera solicitud');
        $this->assertSame(90, $expireCallArgs[0]['seconds'], 'El TTL debe coincidir con windowSeconds');
        $this->assertStringStartsWith('rate_limit:', $expireCallArgs[0]['key']);
    }
}
