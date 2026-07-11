<?php

declare(strict_types=1);

namespace App\Queue;

use App\Core\Container;
use App\Jobs\Job;
use App\Services\QueueService;
use App\Jobs\InvoiceReminderJob;
use App\Jobs\RetainerBillingJob;
use App\Jobs\AppointmentReminderJob;
use PDO;
use Throwable;
use App\Repositories\NotificacionRepository;

final class JobRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Container $container,
        private readonly QueueService $queue
    ) {
    }

    public function work(string $queue = 'default', bool $once = false): void
    {
        $this->scheduleRecurring();
        do {
            $job = $this->fetchAndLock($queue);
            if ($job === null) {
                if ($once) {
                    return;
                }
                sleep(1);
                continue;
            }
            $this->run($job);
        } while (!$once);
    }

    private function scheduleRecurring(): void
    {
        if ($this->queue->shouldRunEveryMinutes('appointment_reminders', 30)) {
            $this->queue->dispatch(AppointmentReminderJob::class, []);
        }
        if ($this->queue->shouldRunDaily('invoice_reminders')) {
            $this->queue->dispatch(InvoiceReminderJob::class, []);
        }
        $now = new \DateTimeImmutable();
        if ($now->format('j') === '1' && $this->queue->shouldRunMonthly('retainer_billing', $now)) {
            $this->queue->dispatch(RetainerBillingJob::class, []);
        }
    }

    /** @param array<string, mixed> $row */
    private function run(array $row): void
    {
        $decoded = $this->queue->decode((string) $row['payload']);
        $jobClass = $decoded['job_class'];
        $payload = $decoded['data'];
        $payload['_attempt'] = (int) $row['attempts'] + 1;
        $startedAt = hrtime(true);
        $job = null;
        try {
            $job = $this->container->get($jobClass);
            if (!$job instanceof Job) {
                throw new \RuntimeException('La clase de job no extiende App\\Jobs\\Job.');
            }
            $job->handle($payload);
            $delete = $this->pdo->prepare('DELETE FROM jobs WHERE id=:id');
            $delete->execute(['id' => (int) $row['id']]);
            $this->recordStats((string) $row['queue'], true, $startedAt);
        } catch (Throwable $exception) {
            $attempts = (int) $row['attempts'] + 1;
            $maxAttempts = $job instanceof Job ? $job->maxAttempts() : 3;
            if ($attempts >= $maxAttempts) {
                if ($job instanceof Job) {
                    $job->onFailure($payload, $exception);
                }
                $this->fail($row, $exception, $jobClass, $payload);
                $this->recordStats((string) $row['queue'], false, $startedAt);
                return;
            }
            $retry = $this->pdo->prepare('UPDATE jobs SET attempts=:attempts,reserved_at=NULL,available_at=:available_at WHERE id=:id');
            $delay = $job instanceof Job ? $job->retryDelay($attempts) : 60 * $attempts;
            $retry->execute(['attempts' => $attempts, 'available_at' => time() + max(1, $delay), 'id' => (int) $row['id']]);
        }
    }

    /** @return array<string, mixed>|null */
    private function fetchAndLock(string $queue): ?array
    {
        $select = $this->pdo->prepare(
            'SELECT * FROM jobs
             WHERE queue=:queue AND available_at<=:now AND reserved_at IS NULL
             ORDER BY id ASC LIMIT 1'
        );
        $select->execute(['queue' => $queue, 'now' => time()]);
        $row = $select->fetch();
        if (!is_array($row)) {
            return null;
        }

        $lock = $this->pdo->prepare('UPDATE jobs SET reserved_at=:now WHERE id=:id AND reserved_at IS NULL');
        $lock->execute(['now' => time(), 'id' => (int) $row['id']]);
        if ($lock->rowCount() !== 1) {
            return null;
        }
        $row['reserved_at'] = time();

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     */
    private function fail(array $row, Throwable $exception, string $jobClass, array $payload): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO failed_jobs (uuid,connection,queue,payload,exception,failed_at)
             VALUES (:uuid,:connection,:queue,:payload,:exception,CURRENT_TIMESTAMP)'
        );
        $insert->execute([
            'uuid' => $this->uuid(),
            'connection' => 'mysql',
            'queue' => (string) $row['queue'],
            'payload' => (string) $row['payload'],
            'exception' => $exception::class . ': ' . $exception->getMessage(),
        ]);
        $failedJobId = (int) $this->pdo->lastInsertId();
        $delete = $this->pdo->prepare('DELETE FROM jobs WHERE id=:id');
        $delete->execute(['id' => (int) $row['id']]);
        $this->notifyFailure($failedJobId, $jobClass, $payload, $exception);
    }

    private function recordStats(string $queue, bool $success, int $startedAt): void
    {
        try {
            $elapsed = max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
            $processedIncrement = $success ? 1 : 0;
            $failedIncrement = $success ? 0 : 1;
            $statement = $this->pdo->prepare(
                'INSERT INTO job_stats
                 (fecha,queue,procesados,fallidos,tiempo_total_ms,tiempo_promedio_ms,created_at,updated_at)
                 VALUES (CURRENT_DATE,:queue,:procesados,:fallidos,:elapsed,:average,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE
                   procesados=procesados+VALUES(procesados),
                   fallidos=fallidos+VALUES(fallidos),
                   tiempo_total_ms=tiempo_total_ms+VALUES(tiempo_total_ms),
                   tiempo_promedio_ms=ROUND(tiempo_total_ms/NULLIF(procesados+fallidos,0)),
                   updated_at=CURRENT_TIMESTAMP'
            );
            $statement->execute([
                'queue' => mb_substr($queue, 0, 50),
                'procesados' => $processedIncrement,
                'fallidos' => $failedIncrement,
                'elapsed' => $elapsed,
                'average' => $elapsed,
            ]);
        } catch (Throwable) {
        }
    }

    /** @param array<string, mixed> $payload */
    private function notifyFailure(int $failedJobId, string $jobClass, array $payload, Throwable $exception): void
    {
        $firmaId = filter_var($payload['firma_id'] ?? null, FILTER_VALIDATE_INT);
        if ($firmaId === false || $firmaId <= 0) {
            return;
        }
        try {
            $notifications = $this->container->get(NotificacionRepository::class);
            $safePayload = json_encode(
                $this->sanitizePayload($payload),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) ?: '{}';
            $message = sprintf(
                '%s: %s. Intentos: %d. Payload: %s',
                basename(str_replace('\\', '/', $jobClass)),
                $exception->getMessage(),
                (int) ($payload['_attempt'] ?? 0),
                $safePayload
            );
            foreach ($notifications->fallbackUsers((int) $firmaId) as $userId) {
                $notifications->createIfMissing([
                    'firma_id' => (int) $firmaId,
                    'usuario_id' => $userId,
                    'titulo' => 'Job fallido',
                    'mensaje' => mb_substr($message, 0, 500),
                    'severidad' => 'critica',
                    'origen_tipo' => 'failed_job',
                    'origen_id' => $failedJobId,
                    'origen_url' => '/superadmin/jobs',
                    'dedupe_key' => hash('sha256', 'failed_job|' . $failedJobId . '|' . $userId),
                ]);
            }
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|document_content/i', (string) $key)) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            } elseif (is_string($value)) {
                $payload[$key] = mb_substr($value, 0, 200);
            }
        }

        return $payload;
    }

    private function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
