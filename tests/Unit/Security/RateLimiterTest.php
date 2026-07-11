<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter();
    }

    public function test_allow_returns_true_on_first_attempt(): void
    {
        $allowed = $this->limiter->allow('user_123', 'POST /clientes', limit: 5, windowSeconds: 60);

        $this->assertTrue($allowed);
    }

    public function test_allow_returns_true_below_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $allowed = $this->limiter->allow('user_123', 'POST /clientes', limit: 5, windowSeconds: 60);
            $this->assertTrue($allowed, "Attempt $i should be allowed");
        }
    }

    public function test_allow_returns_false_when_limit_exceeded(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->allow('user_123', 'POST /clientes', limit: 3, windowSeconds: 60);
        }

        $allowed = $this->limiter->allow('user_123', 'POST /clientes', limit: 3, windowSeconds: 60);

        $this->assertFalse($allowed);
    }

    public function test_different_clients_have_separate_limits(): void
    {
        $this->limiter->allow('user_1', 'POST /clientes', limit: 1);

        $allowed = $this->limiter->allow('user_2', 'POST /clientes', limit: 1);

        $this->assertTrue($allowed);
    }

    public function test_reset_clears_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->allow('user_123', 'POST /clientes', limit: 3);
        }

        $this->limiter->reset('user_123', 'POST /clientes');

        $allowed = $this->limiter->allow('user_123', 'POST /clientes', limit: 3);

        $this->assertTrue($allowed);
    }

    public function test_get_attempts_returns_correct_count(): void
    {
        $this->limiter->allow('user_123', 'GET /reveal');
        $this->limiter->allow('user_123', 'GET /reveal');

        $attempts = $this->limiter->getAttempts('user_123', 'GET /reveal');

        $this->assertSame(2, $attempts);
    }

    public function test_get_retry_after_returns_valid_seconds(): void
    {
        $this->limiter->allow('user_123', 'POST /login', limit: 1, windowSeconds: 60);

        $retryAfter = $this->limiter->getRetryAfter('user_123', 'POST /login', windowSeconds: 60);

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_persistent_limit_blocks_sixth_public_request(): void
    {
        $client = 'test:' . bin2hex(random_bytes(8));
        $endpoint = 'POST:/intake/test/form';
        $path = dirname(__DIR__, 3) . '/storage/locks/rate-limit/'
            . hash('sha256', $client . ':' . $endpoint) . '.json';

        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->assertTrue(
                    $this->limiter->allowPersistent($client, $endpoint, limit: 5, windowSeconds: 60)
                );
            }
            $this->assertFalse(
                $this->limiter->allowPersistent($client, $endpoint, limit: 5, windowSeconds: 60)
            );
            $this->assertGreaterThan(0, $this->limiter->getPersistentRetryAfter($client, $endpoint));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
