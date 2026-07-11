<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Jobs\WebhookDispatchJob;
use App\Services\QueueService;
use App\Services\SensitiveDataService;
use App\Services\WebhookService;
use PDO;
use PHPUnit\Framework\TestCase;

final class WebhookServiceTest extends TestCase
{
    public function testDispatchQueuesOnlyMatchingWebhookFromFirma(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE webhooks (
                id INTEGER PRIMARY KEY, firma_id INTEGER, url TEXT, eventos TEXT, activo INTEGER,
                deleted_at TEXT, created_at TEXT, updated_at TEXT
             );
             CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, payload TEXT, attempts INTEGER,
                reserved_at INTEGER, available_at INTEGER, created_at INTEGER
             );
             INSERT INTO webhooks VALUES
                (1,1,'https://one.example.test','[\"matter.created\"]',1,NULL,NULL,NULL),
                (2,1,'https://two.example.test','[\"invoice.sent\"]',1,NULL,NULL,NULL),
                (3,2,'https://other.example.test','[\"matter.created\"]',1,NULL,NULL,NULL);"
        );
        $_SESSION = [];
        $auth = new Auth(new Session(['name' => 'webhook_test_' . bin2hex(random_bytes(4)), 'secure' => false]));
        $queue = new QueueService($pdo);
        $service = new WebhookService($pdo, $auth, $queue, new SensitiveDataService());

        $service->dispatch(1, 'matter.created', ['id' => 99]);

        $row = $pdo->query('SELECT queue,payload FROM jobs')->fetch();
        self::assertIsArray($row);
        self::assertSame('webhooks', $row['queue']);
        $decoded = $queue->decode((string) $row['payload']);
        self::assertSame(WebhookDispatchJob::class, $decoded['job_class']);
        self::assertSame(1, $decoded['data']['firma_id']);
        self::assertSame(1, $decoded['data']['webhook_id']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }
}
