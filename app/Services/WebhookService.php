<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Jobs\WebhookDispatchJob;
use PDO;

final class WebhookService
{
    public const EVENTS = [
        'matter.created',
        'matter.updated',
        'matter.closed',
        'invoice.sent',
        'invoice.paid',
        'invoice.overdue',
        'lead.created',
        'lead.converted',
        'document.uploaded',
        'payment.received',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly Auth $auth,
        private readonly QueueService $queue,
        private readonly SensitiveDataService $sensitive
    ) {
    }

    /**
     * @param list<string> $events
     * @return array{id: int, secret: string}
     */
    public function register(int $firmaId, string $url, array $events, ?string $secret = null): array
    {
        $url = $this->validUrl($url);
        $events = $this->validEvents($events);
        $plainSecret = $secret ?? bin2hex(random_bytes(32));
        if (strlen($plainSecret) < 32) {
            throw new HttpException(422, 'El secreto del webhook debe tener al menos 32 caracteres.');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO webhooks
             (firma_id,url,eventos,secret_hash,secret_encrypted,activo,created_by_usuario_id,created_at,updated_at)
             VALUES (:firma_id,:url,:eventos,:secret_hash,:secret_encrypted,1,:usuario_id,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'url' => $url,
            'eventos' => json_encode($events, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'secret_hash' => hash('sha256', $plainSecret),
            'secret_encrypted' => $this->sensitive->encrypt($plainSecret),
            'usuario_id' => $this->auth->id(),
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'secret' => $plainSecret];
    }

    /**
     * @deprecated Use register().
     * @param list<string> $eventos
     * @return array{id: int, secret: string}
     */
    public function registrar(int $firmaId, string $url, array $eventos, ?string $secret = null): array
    {
        return $this->register($firmaId, $url, $eventos, $secret);
    }

    /** @return list<array<string, mixed>> */
    public function list(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,url,eventos,activo,created_at,updated_at
             FROM webhooks WHERE firma_id=:firma_id AND deleted_at IS NULL ORDER BY id DESC'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return array_map($this->decode(...), $statement->fetchAll());
    }

    /** @param list<string> $events */
    public function update(int $firmaId, int $id, string $url, array $events, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE webhooks SET url=:url,eventos=:eventos,activo=:activo,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'url' => $this->validUrl($url),
            'eventos' => json_encode($this->validEvents($events), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'activo' => $active ? 1 : 0,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);
        if ($statement->rowCount() === 0 && $this->find($firmaId, $id) === null) {
            throw new HttpException(404, 'El webhook no existe en la firma.');
        }
    }

    public function delete(int $firmaId, int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE webhooks SET activo=0,deleted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        if ($statement->rowCount() !== 1) {
            throw new HttpException(404, 'El webhook no existe en la firma.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function deliveries(int $firmaId, int $id): array
    {
        if ($this->find($firmaId, $id) === null) {
            throw new HttpException(404, 'El webhook no existe en la firma.');
        }
        $statement = $this->pdo->prepare(
            'SELECT id,evento,status_http,respuesta,intento,entregado_at,error,created_at
             FROM webhook_entregas
             WHERE firma_id=:firma_id AND webhook_id=:webhook_id ORDER BY id DESC LIMIT 200'
        );
        $statement->execute(['firma_id' => $firmaId, 'webhook_id' => $id]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(int $firmaId, string $event, array $payload): void
    {
        if (!in_array($event, self::EVENTS, true)) {
            return;
        }
        $statement = $this->pdo->prepare(
            'SELECT id,eventos FROM webhooks
             WHERE firma_id=:firma_id AND activo=1 AND deleted_at IS NULL'
        );
        $statement->execute(['firma_id' => $firmaId]);
        foreach ($statement->fetchAll() as $webhook) {
            $events = json_decode((string) $webhook['eventos'], true);
            if (!is_array($events) || !in_array($event, $events, true)) {
                continue;
            }
            $this->queue->dispatch(WebhookDispatchJob::class, [
                'firma_id' => $firmaId,
                'webhook_id' => (int) $webhook['id'],
                'evento' => $event,
                'payload' => [
                    'id' => bin2hex(random_bytes(12)),
                    'event' => $event,
                    'created_at' => gmdate('c'),
                    'data' => $payload,
                ],
            ], 'webhooks');
        }
    }

    /** @return array<string, mixed>|null */
    private function find(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,url,eventos,activo,created_at,updated_at
             FROM webhooks WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->decode($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $events = json_decode((string) ($row['eventos'] ?? '[]'), true);
        $row['eventos'] = is_array($events) ? $events : [];
        $row['activo'] = (bool) $row['activo'];

        return $row;
    }

    private function validUrl(string $url): string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new HttpException(422, 'La URL del webhook no es valida.');
        }

        return mb_substr($url, 0, 500);
    }

    /**
     * @param list<string> $events
     * @return list<string>
     */
    private function validEvents(array $events): array
    {
        $events = array_values(array_unique(array_filter(array_map('strval', $events))));
        if ($events === [] || array_diff($events, self::EVENTS) !== []) {
            throw new HttpException(422, 'Seleccione eventos de webhook validos.');
        }

        return $events;
    }
}
