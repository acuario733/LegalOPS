<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\NotificacionRepository;
use DateTimeImmutable;
use Throwable;

final class NotificacionService
{
    public function __construct(
        private readonly NotificacionRepository $repository,
        private readonly AuditoriaService $audit,
        // @phpstan-ignore property.onlyWritten
        private readonly Auth $auth
    ) {
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function list(int $firmaId, int $usuarioId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $result = $this->repository->paginate($firmaId, $usuarioId, $this->normalizeFilters($filters), max(1, $page), min(100, max(1, $perPage)));
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    public function markRead(int $firmaId, int $usuarioId, int $id, Request $request): void
    {
        $notification = $this->repository->findForUser($firmaId, $usuarioId, $id) ?? throw new HttpException(404, 'La notificacion no existe.');
        if (($notification['estado'] ?? '') !== 'pendiente') {
            return;
        }
        if ($this->repository->markRead($firmaId, $usuarioId, $id)) {
            $this->audit->record('NOTIFICACION_LEIDA', 'notificaciones', 'notificacion', $id, [], $request, $firmaId);
        }
    }

    /** @return array{created: int, checked: int, errors: int} */
    public function generateAll(?Request $request = null): array
    {
        $created = 0;
        $checked = 0;
        $errors = 0;

        try {
            foreach ($this->repository->dueTerms() as $term) {
                $checked++;
                try {
                    $created += $this->notifyRow($term, 'termino', '/terminos/' . (int) $term['id'], $this->termSeverity((string) $term['fecha_vencimiento']), 'Termino: ' . $term['titulo'], 'Vence el ' . $term['fecha_vencimiento']);
                } catch (Throwable $exception) {
                    $errors++;
                    $this->recordRowError($exception, 'termino', $term, $request);
                }
            }
            foreach ($this->repository->upcomingAudiences() as $audience) {
                $checked++;
                try {
                    $created += $this->notifyRow($audience, 'audiencia', '/audiencias/' . (int) $audience['id'], 'warning', 'Audiencia: ' . $audience['titulo'], 'Programada para ' . $audience['fecha'] . ' ' . $audience['hora']);
                } catch (Throwable $exception) {
                    $errors++;
                    $this->recordRowError($exception, 'audiencia', $audience, $request);
                }
            }
            foreach ($this->repository->dueTasks() as $task) {
                $checked++;
                try {
                    $created += $this->notifyRow($task, 'tarea', '/tareas/' . (int) $task['id'], $this->taskSeverity($task['fecha_vencimiento']), 'Tarea: ' . $task['titulo'], 'Vence el ' . $task['fecha_vencimiento']);
                } catch (Throwable $exception) {
                    $errors++;
                    $this->recordRowError($exception, 'tarea', $task, $request);
                }
            }
            $this->audit->record('NOTIFICACIONES_GENERADAS', 'notificaciones', null, null, [
                'created' => $created,
                'checked' => $checked,
                'errors' => $errors,
            ], $request, null);
        } catch (Throwable $exception) {
            $errors++;
            $errorType = get_class($exception);
            $this->audit->record('NOTIFICACIONES_ERROR', 'notificaciones', null, null, [
                'error_type' => $errorType,
                'error_ref' => hash('sha256', $errorType . '|' . $exception->getMessage()),
            ], $request, null, 'error');
            throw $exception;
        }

        return ['created' => $created, 'checked' => $checked, 'errors' => $errors];
    }

    public function alertarSaldoBajoTrust(int $firmaId, int $trustAccountId, float $umbral): void
    {
        $account = $this->repository->trustAccount($firmaId, $trustAccountId);
        if ($account === null || (float) $account['saldo'] > $umbral) {
            return;
        }
        foreach ($this->repository->fallbackUsers($firmaId) as $userId) {
            $dedupe = hash('sha256', $firmaId . '|' . $userId . '|trust_saldo_bajo|' . $trustAccountId . '|' . number_format($umbral, 2, '.', ''));
            $this->repository->createIfMissing([
                'firma_id' => $firmaId,
                'usuario_id' => $userId,
                'titulo' => 'Saldo trust bajo',
                'mensaje' => mb_substr('La cuenta trust de ' . (string) $account['cliente_nombre'] . ' tiene saldo ' . (string) $account['saldo'] . ' ' . (string) $account['moneda'] . '.', 0, 500),
                'severidad' => 'critica',
                'origen_tipo' => 'trust',
                'origen_id' => $trustAccountId,
                'origen_url' => '/finanzas/trust/libro?cliente_id=' . (int) $account['cliente_id'],
                'dedupe_key' => $dedupe,
            ]);
        }
    }

    /** @param array<string, mixed> $row */
    private function notifyRow(array $row, string $type, string $url, string $severity, string $title, string $message): int
    {
        $userIds = $this->recipientsFor($row);
        if ($userIds === []) {
            throw new \RuntimeException('No active internal recipient for notification.');
        }
        $created = 0;
        foreach (array_unique(array_filter($userIds)) as $userId) {
            $dedupe = hash('sha256', (int) $row['firma_id'] . '|' . $userId . '|' . $type . '|' . (int) $row['id']);
            $created += $this->repository->createIfMissing([
                'firma_id' => (int) $row['firma_id'],
                'usuario_id' => $userId,
                'titulo' => mb_substr($title, 0, 180),
                'mensaje' => mb_substr($message, 0, 500),
                'severidad' => $severity,
                'origen_tipo' => $type,
                'origen_id' => (int) $row['id'],
                'origen_url' => $url,
                'dedupe_key' => $dedupe,
            ]) ? 1 : 0;
        }

        return $created;
    }

    /** @param array<string, mixed> $row @return list<int> */
    private function recipientsFor(array $row): array
    {
        $firmaId = (int) $row['firma_id'];
        $responsibleId = $row['responsable_usuario_id'] ?? null;
        if ($responsibleId !== null && $this->repository->activeInternalUser($firmaId, (int) $responsibleId) !== null) {
            return [(int) $responsibleId];
        }

        return $this->repository->fallbackUsers($firmaId);
    }

    /** @param array<string, mixed> $row */
    private function recordRowError(Throwable $exception, string $type, array $row, ?Request $request): void
    {
        $errorType = get_class($exception);
        $this->audit->record('NOTIFICACIONES_ERROR', 'notificaciones', $type, (int) ($row['id'] ?? 0), [
            'origen_tipo' => $type,
            'firma_id' => (int) ($row['firma_id'] ?? 0),
            'error_type' => $errorType,
            'error_ref' => hash('sha256', $errorType . '|' . $exception->getMessage()),
        ], $request, (int) ($row['firma_id'] ?? 0), 'error');
    }

    private function termSeverity(string $date): string
    {
        return (new DateTimeImmutable($date)) < new DateTimeImmutable('today') ? 'critica' : 'warning';
    }

    private function taskSeverity(mixed $date): string
    {
        return $date !== null && (new DateTimeImmutable((string) $date)) < new DateTimeImmutable('today') ? 'critica' : 'warning';
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'estado' => in_array(($filters['estado'] ?? ''), ['pendiente', 'leida'], true) ? $filters['estado'] : '',
            'severidad' => in_array(($filters['severidad'] ?? ''), ['info', 'warning', 'critica'], true) ? $filters['severidad'] : '',
        ];
    }
}
