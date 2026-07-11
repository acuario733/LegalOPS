<?php

declare(strict_types=1);

namespace App\Security;

use Predis\Client;

final class ApiRateLimitService
{
    /** @var array<int, list<int>> */
    private static array $memory = [];

    public function __construct(private readonly ?Client $redis = null, private readonly int $limit = 1000)
    {
    }

    /** @return array{allowed: bool,remaining: int,retry_after: int,reset_at: int} */
    public function check(int $tokenId, ?int $now = null): array
    {
        $now ??= time();
        $threshold = $now - 3600;
        if ($this->redis !== null) {
            try {
                $key = 'ratelimit:apiv1:' . $tokenId;
                $member = $now . '-' . bin2hex(random_bytes(8));
                $this->redis->executeRaw(['ZREMRANGEBYSCORE', $key, '-inf', (string) $threshold]);
                $this->redis->executeRaw(['ZADD', $key, (string) $now, $member]);
                $this->redis->executeRaw(['EXPIRE', $key, '3600']);
                $count = (int) $this->redis->executeRaw(['ZCARD', $key]);
                $oldest = $this->redis->executeRaw(['ZRANGE', $key, '0', '0', 'WITHSCORES']);
                $oldestScore = is_array($oldest) && isset($oldest[1]) ? (int) $oldest[1] : $now;

                return $this->result($count, $oldestScore, $now);
            } catch (\Throwable) {
            }
        }
        $entries = array_values(array_filter(
            self::$memory[$tokenId] ?? [],
            static fn (int $timestamp): bool => $timestamp > $threshold
        ));
        $entries[] = $now;
        self::$memory[$tokenId] = $entries;

        return $this->result(count($entries), $entries[0], $now);
    }

    /** @return array{allowed: bool,remaining: int,retry_after: int,reset_at: int} */
    private function result(int $count, int $oldest, int $now): array
    {
        $reset = $oldest + 3600;

        return [
            'allowed' => $count <= $this->limit,
            'remaining' => max(0, $this->limit - $count),
            'retry_after' => max(1, $reset - $now),
            'reset_at' => $reset,
        ];
    }
}
