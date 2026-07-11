<?php

declare(strict_types=1);

namespace App\Jobs;

abstract class Job
{
    /** @param array<string, mixed> $payload */
    abstract public function handle(array $payload): void;

    /** @param array<string, mixed> $payload */
    public function onFailure(array $payload, \Throwable $exception): void
    {
    }

    public function maxAttempts(): int
    {
        return 3;
    }

    public function retryDelay(int $failedAttempt): int
    {
        return 60 * max(1, $failedAttempt);
    }
}
