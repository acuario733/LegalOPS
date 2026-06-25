<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Audit
{
    private Closure $sink;

    public function __construct(callable|string $sink)
    {
        if (is_string($sink)) {
            $this->sink = static function (array $event) use ($sink): void {
                $directory = dirname($sink);
                if (!is_dir($directory)) {
                    mkdir($directory, 0770, true);
                }
                $encoded = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($encoded)) {
                    file_put_contents($sink, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
                }
            };
        } else {
            $this->sink = Closure::fromCallable($sink);
        }
    }

    /** @param array<string, mixed> $event */
    public function record(array $event): void
    {
        $record = [
            'timestamp' => gmdate('c'),
            'firma_id' => $event['firma_id'] ?? null,
            'usuario_id' => $event['usuario_id'] ?? null,
            'action' => (string) ($event['action'] ?? 'UNSPECIFIED'),
            'module' => (string) ($event['module'] ?? 'core'),
            'entity_type' => $event['entity_type'] ?? null,
            'entity_id' => $event['entity_id'] ?? null,
            'severity' => (string) ($event['severity'] ?? 'info'),
            'ip' => $event['ip'] ?? null,
            'user_agent' => isset($event['user_agent']) ? mb_substr((string) $event['user_agent'], 0, 255) : null,
            'metadata' => $this->sanitizeMetadata((array) ($event['metadata'] ?? [])),
        ];

        ($this->sink)($record);
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];
        foreach ($metadata as $key => $value) {
            if (preg_match('/password|passwd|secret|token|authorization|cookie|totp|recovery|document_content/i', (string) $key)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;
            }
        }

        return $sanitized;
    }
}

