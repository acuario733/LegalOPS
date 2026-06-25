<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\AuditoriaRepository;

final class AuditoriaService
{
    public function __construct(
        private readonly AuditoriaRepository $repository,
        private readonly Auth $auth
    ) {
    }

    /** @param array<string, mixed> $metadata */
    public function record(
        string $action,
        string $module,
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $firmaId = null,
        string $severity = 'info'
    ): int {
        return $this->repository->insert([
            'firma_id' => $firmaId ?? $this->nullableInt($this->auth->firmaId()),
            'usuario_id' => $this->nullableInt($this->auth->id()),
            'accion' => $action,
            'modulo' => $module,
            'entidad_tipo' => $entityType,
            'entidad_id' => $entityId === null ? null : (string) $entityId,
            'severidad' => $severity,
            'correlation_id' => $this->correlationId($request),
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : mb_substr($request->userAgent(), 0, 255),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function listForFirma(int $firmaId, array $filters, int $page = 1, int $perPage = 30): array
    {
        return $this->repository->paginate($firmaId, $filters, $page, min(100, max(1, $perPage)));
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function listGlobal(array $filters, int $page = 1, int $perPage = 30): array
    {
        return $this->repository->paginate(null, $filters, $page, min(100, max(1, $perPage)), true);
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function sanitize(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (preg_match('/password|secret|token|cookie|authorization|totp|recovery|contenido/i', (string) $key)) {
                $safe[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;
            }
        }

        return $safe;
    }

    private function nullableInt(int|string|null $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    private function correlationId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }
        $provided = trim((string) $request->header('x-correlation-id', ''));
        if ($provided !== '' && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $provided) === 1) {
            return $provided;
        }

        return bin2hex(random_bytes(8));
    }
}
