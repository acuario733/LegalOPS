<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class JobMonitorService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly QueueService $queue
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'pending' => $this->jobs('reserved_at IS NULL'),
            'processing' => $this->jobs('reserved_at IS NOT NULL'),
            'failed' => $this->failedJobs(),
            'stats' => $this->stats(),
        ];
    }

    public function retryFailed(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $select = $this->pdo->prepare('SELECT id,queue,payload FROM failed_jobs WHERE id=:id' . $lock);
            $select->execute(['id' => $id]);
            $failed = $select->fetch();
            if (!is_array($failed)) {
                throw new HttpException(404, 'El job fallido no existe.');
            }

            $decoded = $this->queue->decode((string) $failed['payload']);
            if ($decoded['job_class'] === '') {
                throw new HttpException(422, 'El payload del job no contiene una clase valida.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO jobs (queue,payload,attempts,reserved_at,available_at,created_at)
                 VALUES (:queue,:payload,0,NULL,:available_at,:created_at)'
            );
            $now = time();
            $insert->execute([
                'queue' => mb_substr((string) $failed['queue'], 0, 50),
                'payload' => (string) $failed['payload'],
                'available_at' => $now,
                'created_at' => $now,
            ]);
            $delete = $this->pdo->prepare('DELETE FROM failed_jobs WHERE id=:id');
            $delete->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    private function jobs(string $condition): array
    {
        $rows = $this->pdo->query(
            'SELECT id,queue,payload,attempts,reserved_at,available_at,created_at
             FROM jobs WHERE ' . $condition . ' ORDER BY id DESC LIMIT 100'
        )->fetchAll();

        return array_map(fn (array $row): array => $this->readable($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    private function failedJobs(): array
    {
        $rows = $this->pdo->query(
            'SELECT id,uuid,queue,payload,exception,failed_at
             FROM failed_jobs ORDER BY id DESC LIMIT 100'
        )->fetchAll();

        return array_map(fn (array $row): array => $this->readable($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    private function stats(): array
    {
        return $this->pdo->query(
            'SELECT fecha,queue,procesados,fallidos,tiempo_promedio_ms
             FROM job_stats ORDER BY fecha DESC,queue ASC LIMIT 30'
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function readable(array $row): array
    {
        $decoded = $this->queue->decode((string) ($row['payload'] ?? ''));
        $row['job_class'] = $decoded['job_class'];
        $row['payload_readable'] = json_encode(
            $this->sanitize($decoded['data']),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '{}';
        unset($row['payload']);

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|document_content/i', (string) $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $data[$key] = mb_substr($value, 0, 500);
            }
        }

        return $data;
    }
}
