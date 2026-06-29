<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\CasoComunicacionRepository;
use App\Repositories\CasoRepository;
use App\Repositories\NotificacionRepository;
use App\Repositories\UsuarioRepository;
use JsonException;
use Throwable;

final class CasoComunicacionService
{
    private const TIPOS = ['email', 'llamada', 'mensaje', 'reunion', 'otro'];
    private const DIRECCIONES = ['entrante', 'saliente', 'interno'];

    public function __construct(
        private readonly CasoComunicacionRepository $repository,
        private readonly CasoRepository $casos,
        private readonly UsuarioRepository $usuarios,
        private readonly NotificacionRepository $notificaciones,
        private readonly Database $database
    ) {
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function list(int $casoId, int $firmaId, array $filters): array
    {
        $this->case($casoId, $firmaId);

        return array_map($this->decodeRow(...), $this->repository->findByCaso($casoId, $firmaId, $this->normalizeFilters($filters)));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(int $casoId, int $firmaId, int $usuarioId, array $data): array
    {
        $this->case($casoId, $firmaId);
        if ($this->usuarios->findForFirma($firmaId, $usuarioId) === null) {
            throw new HttpException(422, 'El usuario no pertenece a la firma.');
        }

        $payload = $this->normalize($casoId, $firmaId, $usuarioId, $data);
        $id = $this->repository->create($payload);

        return $this->repository->findById($id, $firmaId) ?? throw new HttpException(500, 'No fue posible recuperar la comunicacion creada.');
    }

    public function delete(int $id, int $firmaId): void
    {
        if (!$this->repository->softDelete($id, $firmaId)) {
            throw new HttpException(404, 'La comunicacion no existe en la firma.');
        }
    }

    public function getEmailAddress(int $casoId, int $firmaId): string
    {
        $this->case($casoId, $firmaId);
        $domain = (string) Config::get('app.inbound_email_domain', 'inbound.legal.com');

        return $this->repository->getOrCreateEmailAddress($casoId, $firmaId, $domain !== '' ? $domain : 'inbound.legal.com');
    }

    /** @param array<string, mixed> $emailData */
    public function processInboundEmail(array $emailData): void
    {
        $to = strtolower((string) ($emailData['to'] ?? ''));
        $token = $this->extractToken($to);
        if ($token === null) {
            return;
        }

        $address = $this->repository->findEmailAddressByToken($token);
        if ($address === null || (int) ($address['activo'] ?? 0) !== 1) {
            return;
        }

        $messageId = trim((string) ($emailData['message_id'] ?? ''));
        if ($messageId !== '' && $this->repository->findByEmailMessageId($messageId) !== null) {
            return;
        }

        $firmaId = (int) $address['firma_id'];
        $casoId = (int) $address['caso_id'];
        $usuarioId = $this->resolveInboundUserId($firmaId, $address);
        if ($usuarioId === null) {
            return;
        }

        $this->database->transaction(function () use ($emailData, $firmaId, $casoId, $usuarioId, $messageId): void {
            $communicationId = $this->repository->create($this->normalize($casoId, $firmaId, $usuarioId, [
                'tipo' => 'email',
                'direccion' => 'entrante',
                'asunto' => (string) ($emailData['subject'] ?? ''),
                'cuerpo' => (string) ($emailData['body_text'] ?? ''),
                'participantes' => [
                    'from' => (string) ($emailData['from'] ?? ''),
                    'to' => (string) ($emailData['to'] ?? ''),
                ],
                'fecha_comunicacion' => date('Y-m-d H:i:s'),
                'adjuntos' => is_array($emailData['attachments'] ?? null) ? $emailData['attachments'] : [],
                'origen' => 'email_bcc',
                'email_message_id' => $messageId !== '' ? $messageId : null,
            ]));
            $this->notifyInboundEmail($firmaId, $casoId, $usuarioId, $communicationId, (string) ($emailData['subject'] ?? 'Correo entrante'));
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(int $casoId, int $firmaId, int $usuarioId, array $data): array
    {
        $tipo = (string) ($data['tipo'] ?? '');
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new HttpException(422, 'Seleccione un tipo de comunicacion valido.');
        }
        $direccion = (string) ($data['direccion'] ?? 'saliente');
        if (!in_array($direccion, self::DIRECCIONES, true)) {
            throw new HttpException(422, 'Seleccione una direccion valida.');
        }
        $date = $this->dateTime((string) ($data['fecha_comunicacion'] ?? ''));
        $subject = trim((string) ($data['asunto'] ?? ''));
        if (mb_strlen($subject) > 500) {
            throw new HttpException(422, 'El asunto no puede superar 500 caracteres.');
        }
        $body = trim((string) ($data['cuerpo'] ?? ($data['notas'] ?? '')));
        if (mb_strlen($body) > 10000) {
            throw new HttpException(422, 'El cuerpo no puede superar 10000 caracteres.');
        }
        $duration = $this->duration($data['duracion_minutos'] ?? null);
        if (in_array($tipo, ['llamada', 'reunion'], true) && $duration === null) {
            throw new HttpException(422, 'La duracion es obligatoria para llamadas y reuniones.');
        }
        if (!in_array($tipo, ['llamada', 'reunion'], true)) {
            $duration = null;
        }

        return [
            'firma_id' => $firmaId,
            'caso_id' => $casoId,
            'tipo' => $tipo,
            'direccion' => $direccion,
            'asunto' => $subject === '' ? null : $subject,
            'cuerpo' => $body === '' ? null : $body,
            'participantes' => $this->json($data['participantes'] ?? null),
            'fecha_comunicacion' => $date,
            'duracion_minutos' => $duration,
            'adjuntos' => $this->json($data['adjuntos'] ?? null),
            'usuario_id' => $usuarioId,
            'origen' => in_array(($data['origen'] ?? 'manual'), ['manual', 'email_bcc', 'sistema'], true) ? (string) ($data['origen'] ?? 'manual') : 'manual',
            'email_message_id' => $this->nullableString($data['email_message_id'] ?? null, 500),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'tipo' => in_array(($filters['tipo'] ?? ''), self::TIPOS, true) ? (string) $filters['tipo'] : '',
            'direccion' => in_array(($filters['direccion'] ?? ''), self::DIRECCIONES, true) ? (string) $filters['direccion'] : '',
            'fecha_desde' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['fecha_desde'] ?? '')) === 1 ? (string) $filters['fecha_desde'] : '',
            'fecha_hasta' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['fecha_hasta'] ?? '')) === 1 ? (string) $filters['fecha_hasta'] : '',
        ];
    }

    /** @return array<string, mixed> */
    private function case(int $casoId, int $firmaId): array
    {
        return $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(404, 'El caso no existe en la firma.');
    }

    private function dateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new HttpException(422, 'La fecha de comunicacion es obligatoria.');
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new HttpException(422, 'La fecha de comunicacion no es valida.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function duration(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $duration = filter_var($value, FILTER_VALIDATE_INT);
        if ($duration === false || $duration < 1 || $duration > 1440) {
            throw new HttpException(422, 'La duracion debe estar entre 1 y 1440 minutos.');
        }

        return (int) $duration;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function json(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                $value = array_values(array_filter(array_map('trim', explode(',', $value))));
            }
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return null;
        }
    }

    private function extractToken(string $email): ?string
    {
        if (preg_match('/caso-([a-f0-9]{32})@/i', $email, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /** @param array<string, mixed> $address */
    private function resolveInboundUserId(int $firmaId, array $address): ?int
    {
        $responsible = $address['responsable_usuario_id'] ?? null;
        if ($responsible !== null && $this->usuarios->findForFirma($firmaId, (int) $responsible) !== null) {
            return (int) $responsible;
        }
        $fallback = $this->notificaciones->fallbackUsers($firmaId);

        return $fallback[0] ?? null;
    }

    private function notifyInboundEmail(int $firmaId, int $casoId, int $usuarioId, int $communicationId, string $subject): void
    {
        try {
            $this->notificaciones->createIfMissing([
                'firma_id' => $firmaId,
                'usuario_id' => $usuarioId,
                'titulo' => 'Correo entrante registrado',
                'mensaje' => mb_substr($subject !== '' ? $subject : 'Nuevo correo asociado al caso.', 0, 500),
                'severidad' => 'info',
                'origen_tipo' => 'caso_comunicacion',
                'origen_id' => $communicationId,
                'origen_url' => '/casos/' . $casoId,
                'dedupe_key' => hash('sha256', $firmaId . '|caso_comunicacion|' . $communicationId),
            ]);
        } catch (Throwable) {
            // Notificacion best-effort: el webhook no debe fallar si la notificacion no pudo registrarse.
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decodeRow(array $row): array
    {
        foreach (['participantes', 'adjuntos'] as $field) {
            if (is_string($row[$field] ?? null)) {
                $decoded = json_decode((string) $row[$field], true);
                $row[$field] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }
        }

        return $row;
    }
}
