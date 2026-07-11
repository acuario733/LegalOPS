<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\SoporteRepository;
use App\Validators\TicketValidator;

final class SoporteService
{
    /** @var array<string, array{primera_respuesta_horas: int, solucion_horas: int}> */
    private const SLA_POLICY = [
        'critica' => ['primera_respuesta_horas' => 2, 'solucion_horas' => 24],
        'alta' => ['primera_respuesta_horas' => 4, 'solucion_horas' => 48],
        'media' => ['primera_respuesta_horas' => 8, 'solucion_horas' => 72],
        'baja' => ['primera_respuesta_horas' => 24, 'solucion_horas' => 120],
    ];

    public function __construct(
        private readonly SoporteRepository $repository,
        private readonly TicketValidator $validator,
        private readonly Database $database,
        private readonly LimitePlanService $limits,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function list(int $firmaId, bool $firmaScope, int $page = 1, int $perPage = 25): array
    {
        $result = $this->repository->paginate($firmaId, $this->userId(), $firmaScope, max(1, $page), min(100, max(1, $perPage)));
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function globalQueue(): array
    {
        return $this->repository->globalQueue();
    }

    /** @return array<string, array{primera_respuesta_horas: int, solucion_horas: int}> */
    public function slaPolicy(): array
    {
        return self::SLA_POLICY;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id, bool $firmaScope = false): array
    {
        $ticket = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El ticket no existe.');
        $this->ensureVisible($ticket, $firmaScope);
        $ticket['mensajes'] = $this->repository->messages($firmaId, $id, false);

        return $ticket;
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $normalized = $this->normalizeTicket($data);
        if (!$this->validator->validateTicket($normalized)) {
            throw new HttpException(422, 'Revise el ticket de soporte.', $this->validator->errors());
        }
        $this->limits->requireCapacity($firmaId, 'tickets_soporte', $request);

        return $this->database->transaction(function () use ($firmaId, $normalized, $request): int {
            $id = $this->repository->create([
                'firma_id' => $firmaId,
                'creado_por_usuario_id' => $this->userId(),
                'asunto' => $normalized['asunto'],
                'categoria' => $normalized['categoria'],
                'prioridad' => $normalized['prioridad'],
                'contexto_json' => json_encode(['url' => $normalized['url']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $this->repository->addMessage($firmaId, $id, $this->userId(), 'firma', $normalized['mensaje']);
            $this->audit->record('TICKET_SOPORTE_CREADO', 'soporte', 'ticket', $id, [
                'prioridad' => $normalized['prioridad'],
                'categoria' => $normalized['categoria'],
                'sla' => self::SLA_POLICY[$normalized['prioridad']] ?? self::SLA_POLICY['media'],
            ], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function addMessage(int $firmaId, int $id, array $data, Request $request, bool $firmaScope = false): int
    {
        $this->find($firmaId, $id, $firmaScope);
        $normalized = [
            'mensaje' => mb_substr(trim((string) ($data['mensaje'] ?? '')), 0, 4000),
            'visibilidad' => (string) ($data['visibilidad'] ?? 'firma'),
        ];
        if (!$this->validator->validateMessage($normalized)) {
            throw new HttpException(422, 'Revise el mensaje.', $this->validator->errors());
        }
        if ($normalized['visibilidad'] === 'superadmin' && ($this->auth->user()['tipo'] ?? null) !== 'superadmin') {
            throw new HttpException(403, 'No puede crear mensajes internos de superadministracion.');
        }
        $messageId = $this->repository->addMessage($firmaId, $id, $this->userId(), $normalized['visibilidad'], $normalized['mensaje']);
        $this->audit->record('TICKET_SOPORTE_MENSAJE', 'soporte', 'ticket_mensaje', $messageId, ['ticket_id' => $id, 'visibilidad' => $normalized['visibilidad']], $request, $firmaId);

        return $messageId;
    }

    /** @param array<string, mixed> $data */
    public function changeStatus(int $firmaId, int $id, array $data, Request $request, bool $firmaScope = false): void
    {
        $before = $this->find($firmaId, $id, $firmaScope);
        $normalized = ['estado' => (string) ($data['estado'] ?? '')];
        if (!$this->validator->validateStatus($normalized)) {
            throw new HttpException(422, 'Estado de ticket no valido.', $this->validator->errors());
        }
        $this->repository->setStatus($firmaId, $id, $normalized['estado']);
        $this->audit->record('TICKET_SOPORTE_ESTADO', 'soporte', 'ticket', $id, [
            'anterior' => $before['estado'],
            'nuevo' => $normalized['estado'],
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalizeTicket(array $data): array
    {
        return [
            'asunto' => mb_substr(trim((string) ($data['asunto'] ?? '')), 0, 180),
            'categoria' => $this->nullableString($data['categoria'] ?? null, 80),
            'prioridad' => (string) ($data['prioridad'] ?? 'media'),
            'mensaje' => mb_substr(trim((string) ($data['mensaje'] ?? '')), 0, 4000),
            'url' => mb_substr(trim((string) ($data['url'] ?? '')), 0, 255),
        ];
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** @param array<string, mixed> $ticket */
    private function ensureVisible(array $ticket, bool $firmaScope): void
    {
        if ($firmaScope) {
            return;
        }
        if ((int) ($ticket['creado_por_usuario_id'] ?? 0) !== $this->userId()) {
            throw new HttpException(404, 'El ticket no existe.');
        }
    }

    private function userId(): int
    {
        $id = $this->auth->id();
        if ($id === null) {
            throw new HttpException(403, 'La operacion requiere usuario autenticado.');
        }

        return (int) $id;
    }
}
