<?php

declare(strict_types=1);

namespace App\Logging;

use App\Core\Config;

final class StructuredLogger
{
    public function __construct(private readonly string $path)
    {
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $record = [
            'ts' => gmdate('c'),
            'level' => strtoupper($level),
            'cid' => $context['correlation_id'] ?? null,
            'firm' => $context['firma_id'] ?? null,
            'uid' => $context['usuario_id'] ?? null,
            'method' => $context['method'] ?? null,
            'path' => $context['path'] ?? null,
            'status' => $context['status'] ?? null,
            'ms' => $context['duration_ms'] ?? null,
            'message' => mb_substr($message, 0, 500),
            'context' => $this->sanitize($context['context'] ?? []),
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($line)) {
            return;
        }
        if ((string) Config::get('app.environment', 'production') === 'production') {
            file_put_contents('php://stdout', $line . PHP_EOL, FILE_APPEND);
            return;
        }
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie/i', (string) $key)) {
                $safe[$key] = '[REDACTED]';
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;
            }
        }

        return $safe;
    }
}
