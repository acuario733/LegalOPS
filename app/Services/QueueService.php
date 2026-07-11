<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;
use PDO;

final class QueueService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(string $jobClass, array $payload, string $queue = 'default', int $delaySeconds = 0): void
    {
        $body = json_encode([
            'job_class' => $jobClass,
            'data' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $statement = $this->pdo->prepare(
            'INSERT INTO jobs (queue,payload,attempts,reserved_at,available_at,created_at)
             VALUES (:queue,:payload,0,NULL,:available_at,:created_at)'
        );
        $now = time();
        $statement->execute([
            'queue' => mb_substr($queue, 0, 50),
            'payload' => $body,
            'available_at' => $now + max(0, $delaySeconds),
            'created_at' => $now,
        ]);
    }

    /** @return array{job_class: string, data: array<string, mixed>} */
    public function decode(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = [];
        }

        return [
            'job_class' => is_string($decoded['job_class'] ?? null) ? $decoded['job_class'] : '',
            'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
        ];
    }

    public function shouldRunDaily(string $key, ?\DateTimeImmutable $now = null): bool
    {
        return $this->claimSchedule($key, 'daily', $now ?? new \DateTimeImmutable());
    }

    public function shouldRunMonthly(string $key, ?\DateTimeImmutable $now = null): bool
    {
        return $this->claimSchedule($key, 'monthly', $now ?? new \DateTimeImmutable());
    }

    public function shouldRunEveryMinutes(string $key, int $minutes, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        $scheduleKey = mb_substr('interval:' . max(1, $minutes) . ':' . $key, 0, 100);
        $statement = $this->pdo->prepare('SELECT last_run_at FROM job_schedules WHERE schedule_key=:schedule_key');
        $statement->execute(['schedule_key' => $scheduleKey]);
        $last = $statement->fetchColumn();
        if (is_string($last) && (new \DateTimeImmutable($last))->modify('+' . max(1, $minutes) . ' minutes') > $now) {
            return false;
        }
        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO job_schedules (schedule_key,last_run_at) VALUES (:schedule_key,:last_run_at)
               ON CONFLICT(schedule_key) DO UPDATE SET last_run_at=excluded.last_run_at'
            : 'INSERT INTO job_schedules (schedule_key,last_run_at) VALUES (:schedule_key,:last_run_at)
               ON DUPLICATE KEY UPDATE last_run_at=VALUES(last_run_at)';
        $claim = $this->pdo->prepare($sql);
        $claim->execute(['schedule_key' => $scheduleKey, 'last_run_at' => $now->format('Y-m-d H:i:s')]);

        return true;
    }

    private function claimSchedule(string $key, string $frequency, \DateTimeImmutable $now): bool
    {
        $scheduleKey = mb_substr($frequency . ':' . $key, 0, 100);
        $statement = $this->pdo->prepare('SELECT last_run_at FROM job_schedules WHERE schedule_key=:schedule_key');
        $statement->execute(['schedule_key' => $scheduleKey]);
        $last = $statement->fetchColumn();
        if (is_string($last) && $last !== '') {
            $lastRun = new \DateTimeImmutable($last);
            $samePeriod = $frequency === 'daily'
                ? $lastRun->format('Y-m-d') === $now->format('Y-m-d')
                : $lastRun->format('Y-m') === $now->format('Y-m');
            if ($samePeriod) {
                return false;
            }
        }
        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO job_schedules (schedule_key,last_run_at) VALUES (:schedule_key,:last_run_at)
               ON CONFLICT(schedule_key) DO UPDATE SET last_run_at=excluded.last_run_at'
            : 'INSERT INTO job_schedules (schedule_key,last_run_at) VALUES (:schedule_key,:last_run_at)
               ON DUPLICATE KEY UPDATE last_run_at=VALUES(last_run_at)';
        $claim = $this->pdo->prepare($sql);
        $claim->execute(['schedule_key' => $scheduleKey, 'last_run_at' => $now->format('Y-m-d H:i:s')]);

        return true;
    }
}
