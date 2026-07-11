<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\JobMonitorService;
use App\Services\QueueService;
use PDO;
use PHPUnit\Framework\TestCase;

final class JobMonitorServiceTest extends TestCase
{
    public function testRetryMovesFailedJobBackToQueue(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, payload TEXT, attempts INTEGER,
                reserved_at INTEGER, available_at INTEGER, created_at INTEGER
             );
             CREATE TABLE failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT, connection TEXT, queue TEXT,
                payload TEXT, exception TEXT, failed_at TEXT
             );
             CREATE TABLE job_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT, queue TEXT, procesados INTEGER,
                fallidos INTEGER, tiempo_promedio_ms INTEGER
             );"
        );
        $queue = new QueueService($pdo);
        $payload = json_encode([
            'job_class' => 'App\\Jobs\\SendEmailJob',
            'data' => ['firma_id' => 1, 'token' => 'must-not-be-rendered'],
        ], JSON_THROW_ON_ERROR);
        $insert = $pdo->prepare(
            'INSERT INTO failed_jobs (uuid,connection,queue,payload,exception,failed_at)
             VALUES (:uuid,:connection,:queue,:payload,:exception,:failed_at)'
        );
        $insert->execute([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'connection' => 'mysql',
            'queue' => 'mail',
            'payload' => $payload,
            'exception' => 'Timeout',
            'failed_at' => '2026-06-30 10:00:00',
        ]);
        $service = new JobMonitorService($pdo, $queue);

        $dashboard = $service->dashboard();
        self::assertSame('[REDACTED]', json_decode($dashboard['failed'][0]['payload_readable'], true)['token']);

        $service->retryFailed(1);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM failed_jobs')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT attempts FROM jobs')->fetchColumn());
    }
}
