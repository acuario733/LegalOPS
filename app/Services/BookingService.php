<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\BookingRepository;
use App\Repositories\ProspectoRepository;
use App\Repositories\UsuarioRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class BookingService
{
    public function __construct(
        private readonly BookingRepository $repository,
        private readonly ProspectoRepository $prospectos,
        private readonly UsuarioRepository $usuarios,
        private readonly Database $database,
        private readonly LocalMailService $mail
    ) {
    }

    /** @return list<string> */
    public function getAvailableSlots(string $slug, string $fecha): array
    {
        $config = $this->repository->findConfigBySlug($slug)
            ?? throw new HttpException(404, 'El enlace de reserva no existe.');

        return $this->availableSlotsForConfig($config, $fecha);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createAppointment(string $slug, array $data): array
    {
        $name = trim((string) ($data['nombre_cliente'] ?? ''));
        $email = strtolower(trim((string) ($data['email_cliente'] ?? '')));
        $phone = $this->nullableString($data['telefono_cliente'] ?? null, 50);
        $notes = $this->nullableString($data['notas'] ?? null, 2000);
        $date = trim((string) ($data['fecha'] ?? ''));
        $time = substr(trim((string) ($data['hora_inicio'] ?? '')), 0, 5);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new HttpException(422, 'Ingrese el nombre completo.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 200) {
            throw new HttpException(422, 'Ingrese un correo electrónico válido.');
        }

        $appointment = $this->database->transaction(function () use (
            $slug,
            $name,
            $email,
            $phone,
            $notes,
            $date,
            $time
        ): array {
            $config = $this->repository->lockConfigBySlug($slug)
                ?? throw new HttpException(404, 'El enlace de reserva no existe.');
            if (!in_array($time, $this->availableSlotsForConfig($config, $date), true)) {
                throw new HttpException(409, 'El horario seleccionado ya no está disponible.');
            }

            $firmaId = (int) $config['firma_id'];
            $reference = $date . ' ' . $time . ' ' . $slug;
            $existing = $this->prospectos->findByEmailForFirma($firmaId, $email);
            if ($existing === null) {
                $prospectId = $this->prospectos->createFromPublicSource([
                    'firma_id' => $firmaId,
                    'nombre' => mb_substr($name, 0, 180),
                    'nombre_normalizado' => mb_strtolower(mb_substr($name, 0, 180)),
                    'email' => $email,
                    'telefono' => $phone,
                    'fuente' => 'booking',
                    'fuente_referencia' => mb_substr($reference, 0, 200),
                    'notas' => $notes === null ? 'Consulta agendada desde booking.' : mb_substr($notes, 0, 1000),
                    'tratamiento_datos_autorizado' => 1,
                ]);
            } else {
                $prospectId = (int) $existing['id'];
                $this->prospectos->updateFromPublicSource(
                    $prospectId,
                    $firmaId,
                    mb_substr($name, 0, 180),
                    $phone,
                    'booking',
                    mb_substr($reference, 0, 200),
                    $notes
                );
            }

            $duration = (int) $config['duracion_minutos'];
            $end = DateTimeImmutable::createFromFormat('!H:i', $time);
            if ($end === false) {
                throw new HttpException(422, 'El horario seleccionado no es válido.');
            }
            $end = $end->add(new DateInterval('PT' . $duration . 'M'));
            $token = bin2hex(random_bytes(32));
            $id = $this->repository->createAppointment([
                'firma_id' => $firmaId,
                'booking_config_id' => (int) $config['id'],
                'usuario_id' => (int) $config['usuario_id'],
                'nombre_cliente' => mb_substr($name, 0, 200),
                'email_cliente' => $email,
                'telefono_cliente' => $phone,
                'fecha' => $date,
                'hora_inicio' => $time . ':00',
                'hora_fin' => $end->format('H:i:s'),
                'notas' => $notes,
                'prospecto_id' => $prospectId,
                'token_cancelacion' => $token,
            ]);

            return $this->repository->findByToken($token)
                ?? throw new HttpException(500, 'No fue posible recuperar la cita creada.');
        });

        $this->sendAppointmentEmails($appointment);

        return $appointment;
    }

    public function cancelByToken(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new HttpException(404, 'El enlace de cancelación no es válido.');
        }
        $appointment = $this->repository->findByToken($token)
            ?? throw new HttpException(404, 'La cita no existe.');
        if ($appointment['estado'] === 'cancelada') {
            return;
        }
        if ($appointment['estado'] !== 'confirmada' || !$this->repository->cancelByToken($token)) {
            throw new HttpException(409, 'La cita ya no puede cancelarse.');
        }
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function listForFirma(int $firmaId, array $filters): array
    {
        return $this->repository->findAppointments($firmaId, $filters);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createConfig(int $firmaId, int $usuarioId, array $data): array
    {
        $user = $this->usuarios->findForFirma($firmaId, $usuarioId);
        if ($user === null) {
            throw new HttpException(422, 'El abogado no pertenece a la firma.');
        }
        if ($this->repository->findConfigByUsuario($usuarioId, $firmaId) !== null) {
            throw new HttpException(409, 'El abogado ya tiene un enlace de reserva.');
        }
        $normalized = $this->normalizeConfig($data);
        $slug = $this->uniqueSlug((string) ($user['nombre'] ?? $normalized['titulo']));
        $id = $this->repository->createConfig($normalized + [
            'firma_id' => $firmaId,
            'usuario_id' => $usuarioId,
            'slug' => $slug,
        ]);

        return $this->repository->findConfigById($id, $firmaId)
            ?? throw new HttpException(500, 'No fue posible recuperar la configuración creada.');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function updateConfig(int $id, int $firmaId, array $data): array
    {
        $current = $this->repository->findConfigById($id, $firmaId)
            ?? throw new HttpException(404, 'La configuración no existe en la firma.');
        $normalized = $this->normalizeConfig($data, $current);
        $this->repository->updateConfig($id, $firmaId, $normalized);

        // @phpstan-ignore nullCoalesce.expr
        return $this->repository->findConfigById($id, $firmaId)
            ?? throw new HttpException(404, 'La configuración no existe en la firma.');
    }

    public function deleteConfig(int $id, int $firmaId): void
    {
        if (!$this->repository->softDeleteConfig($id, $firmaId)) {
            throw new HttpException(404, 'La configuración no existe en la firma.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function configsForFirma(int $firmaId): array
    {
        return $this->repository->findConfigsForFirma($firmaId);
    }

    /** @return array<string, mixed>|null */
    public function configForUser(int $usuarioId, int $firmaId): ?array
    {
        return $this->repository->findConfigByUsuario($usuarioId, $firmaId);
    }

    /** @return array<string, mixed> */
    public function publicConfig(string $slug): array
    {
        $config = $this->repository->findConfigBySlug($slug)
            ?? throw new HttpException(404, 'El enlace de reserva no existe.');
        if ((int) $config['activo'] !== 1 || ($config['firma_estado'] ?? '') !== 'activa') {
            throw new HttpException(404, 'El enlace de reserva no está disponible.');
        }
        $config['dias_activos'] = $this->decodeDays($config['dias_activos']);

        return $config;
    }

    /** @return array<string, mixed> */
    public function appointmentByToken(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new HttpException(404, 'El enlace de cancelación no es válido.');
        }

        return $this->repository->findByToken($token)
            ?? throw new HttpException(404, 'La cita no existe.');
    }

    public function completeAppointment(int $id, int $firmaId): void
    {
        if (!$this->repository->completeAppointment($id, $firmaId)) {
            throw new HttpException(409, 'La cita no existe o no puede marcarse como completada.');
        }
    }

    /** @param array<string, mixed> $config @return list<string> */
    private function availableSlotsForConfig(array $config, string $dateValue): array
    {
        if ((int) $config['activo'] !== 1 || ($config['firma_estado'] ?? 'activa') !== 'activa') {
            throw new HttpException(404, 'El enlace de reserva no está disponible.');
        }
        $timezone = $this->timezone((string) ($config['firma_timezone'] ?? 'UTC'));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue, $timezone);
        if ($date === false || $date->format('Y-m-d') !== $dateValue) {
            throw new HttpException(422, 'La fecha seleccionada no es válida.');
        }
        $today = new DateTimeImmutable('today', $timezone);
        $minimum = $today->add(new DateInterval('P' . max(0, (int) $config['dias_anticipacion_min']) . 'D'));
        $maximum = $today->add(new DateInterval('P' . max(0, (int) $config['dias_anticipacion_max']) . 'D'));
        if ($date < $minimum || $date > $maximum) {
            throw new HttpException(422, 'La fecha está fuera del rango disponible.');
        }
        if (!in_array((int) $date->format('N'), $this->decodeDays($config['dias_activos']), true)) {
            return [];
        }

        $duration = (int) $config['duracion_minutos'];
        $buffer = (int) $config['buffer_entre_citas'];
        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $dateValue . ' ' . $config['hora_inicio'],
            $timezone
        );
        $end = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $dateValue . ' ' . $config['hora_fin'],
            $timezone
        );
        if ($start === false || $end === false || $duration < 1 || $start >= $end) {
            throw new HttpException(422, 'La configuración horaria no es válida.');
        }

        $occupied = $this->repository->getOccupiedSlots((int) $config['id'], $dateValue);
        $now = new DateTimeImmutable('now', $timezone);
        $slots = [];
        $cursor = $start;
        $step = new DateInterval('PT' . ($duration + $buffer) . 'M');
        $slotDuration = new DateInterval('PT' . $duration . 'M');
        while ($cursor->add($slotDuration) <= $end) {
            $time = $cursor->format('H:i');
            if (!in_array($time, $occupied, true) && ($dateValue !== $today->format('Y-m-d') || $cursor > $now)) {
                $slots[] = $time;
            }
            $cursor = $cursor->add($step);
        }

        return $slots;
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $current @return array<string, mixed> */
    private function normalizeConfig(array $data, ?array $current = null): array
    {
        $title = trim((string) ($data['titulo'] ?? ($current['titulo'] ?? 'Consulta legal')));
        $duration = (int) ($data['duracion_minutos'] ?? ($current['duracion_minutos'] ?? 30));
        $buffer = (int) ($data['buffer_entre_citas'] ?? ($current['buffer_entre_citas'] ?? 0));
        $min = (int) ($data['dias_anticipacion_min'] ?? ($current['dias_anticipacion_min'] ?? 1));
        $max = (int) ($data['dias_anticipacion_max'] ?? ($current['dias_anticipacion_max'] ?? 30));
        $days = $this->decodeDays($data['dias_activos'] ?? ($current['dias_activos'] ?? [1, 2, 3, 4, 5]));
        $start = $this->normalizeTime($data['hora_inicio'] ?? ($current['hora_inicio'] ?? '09:00:00'));
        $end = $this->normalizeTime($data['hora_fin'] ?? ($current['hora_fin'] ?? '18:00:00'));
        $email = $this->nullableString($data['notificar_email'] ?? ($current['notificar_email'] ?? null), 200);

        if ($title === '' || mb_strlen($title) > 200) {
            throw new HttpException(422, 'El título es obligatorio y admite máximo 200 caracteres.');
        }
        if (!in_array($duration, [15, 30, 45, 60], true)) {
            throw new HttpException(422, 'Seleccione una duración válida.');
        }
        if (!in_array($buffer, [0, 5, 10, 15], true)) {
            throw new HttpException(422, 'Seleccione un buffer válido.');
        }
        if ($days === []) {
            throw new HttpException(422, 'Seleccione al menos un día activo.');
        }
        if ($start >= $end) {
            throw new HttpException(422, 'La hora final debe ser posterior a la hora inicial.');
        }
        if ($min < 0 || $max < $min || $max > 365) {
            throw new HttpException(422, 'El rango de anticipación no es válido.');
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'El correo de notificación no es válido.');
        }

        return [
            'titulo' => $title,
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($current['descripcion'] ?? null), 2000),
            'duracion_minutos' => $duration,
            'dias_activos' => json_encode($days, JSON_THROW_ON_ERROR),
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'buffer_entre_citas' => $buffer,
            'dias_anticipacion_min' => $min,
            'dias_anticipacion_max' => $max,
            'activo' => $this->truthy($data['activo'] ?? ($current['activo'] ?? 1)) ? 1 : 0,
            'notificar_email' => $email === null ? null : strtolower($email),
        ];
    }

    /** @param array<string, mixed> $appointment */
    private function sendAppointmentEmails(array $appointment): void
    {
        $baseUrl = rtrim((string) Config::get('app.url', ''), '/');
        $cancelUrl = $baseUrl . '/booking/cancelar/' . rawurlencode((string) $appointment['token_cancelacion']);
        $body = sprintf(
            "Su cita con %s está confirmada.\nFecha: %s\nHora: %s\nCancelar: %s",
            $appointment['firma_nombre'],
            $appointment['fecha'],
            substr((string) $appointment['hora_inicio'], 0, 5),
            $cancelUrl
        );
        foreach (
            array_unique(array_filter([
            (string) $appointment['email_cliente'],
            (string) ($appointment['notificar_email'] ?? ''),
            (string) ($appointment['usuario_email'] ?? ''),
            ])) as $email
        ) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            try {
                $this->mail->send($email, 'Confirmación de cita - ' . $appointment['firma_nombre'], $body);
            } catch (Throwable $exception) {
                error_log('[BookingService] No fue posible encolar confirmación: ' . $exception->getMessage());
            }
        }
    }

    /** @return list<int> */
    private function decodeDays(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        $days = array_values(array_unique(array_filter(
            array_map(static fn (mixed $day): int => (int) $day, (array) $value),
            static fn (int $day): bool => $day >= 1 && $day <= 7
        )));
        sort($days);

        return $days;
    }

    private function uniqueSlug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? $value : $ascii)) ?? '', '-');
        $base = $base === '' ? 'consulta' : mb_substr($base, 0, 90);
        $slug = $base;
        while ($this->repository->slugExists($slug)) {
            $slug = $base . '-' . strtolower(bin2hex(random_bytes(4)));
        }

        return $slug;
    }

    private function normalizeTime(mixed $value): string
    {
        $value = trim((string) $value);
        $time = DateTimeImmutable::createFromFormat('!H:i', substr($value, 0, 5));
        if ($time === false) {
            throw new HttpException(422, 'Ingrese una hora válida.');
        }

        return $time->format('H:i:s');
    }

    private function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'si', 'yes'], true);
    }
}
