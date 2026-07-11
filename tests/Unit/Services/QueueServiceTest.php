<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Jobs\SendEmailJob;
use App\Services\QueueService;
use PDO;
use PHPUnit\Framework\TestCase;

final class QueueServiceTest extends TestCase
{
    public function testDispatchInsertsJobPayload(): void
    {
        // Arrange
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );
        $service = new QueueService($pdo);

        // Act
        $service->dispatch(SendEmailJob::class, ['to' => 'test@example.com'], 'emails', 30);

        // Assert
        $row = $pdo->query('SELECT queue,payload,attempts,reserved_at,available_at,created_at FROM jobs')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('emails', $row['queue']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['reserved_at']);
        self::assertGreaterThanOrEqual((int) $row['created_at'] + 30, (int) $row['available_at']);

        $decoded = $service->decode((string) $row['payload']);
        self::assertSame(SendEmailJob::class, $decoded['job_class']);
        self::assertSame('test@example.com', $decoded['data']['to']);
    }

    public function testDailyScheduleCanOnlyBeClaimedOncePerDay(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE job_schedules (schedule_key TEXT PRIMARY KEY,last_run_at TEXT NOT NULL)');
        $service = new QueueService($pdo);
        $now = new \DateTimeImmutable('2026-06-29 08:00:00');

        self::assertTrue($service->shouldRunDaily('invoice_reminders', $now));
        self::assertFalse($service->shouldRunDaily('invoice_reminders', $now->modify('+2 hours')));
        self::assertTrue($service->shouldRunDaily('invoice_reminders', $now->modify('+1 day')));
    }
}
