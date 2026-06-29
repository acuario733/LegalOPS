<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class BookingRepository extends BaseRepository
{
    /**
     * PUBLIC LOOKUP EXCEPTION: the globally unique slug is the public tenant locator.
     *
     * @return array<string, mixed>|null
     */
    public function findConfigBySlug(string $slug): ?array
    {
        return $this->configBySlug($slug, false);
    }

    /** @return array<string, mixed>|null */
    public function lockConfigBySlug(string $slug): ?array
    {
        return $this->configBySlug($slug, true);
    }

    /** @return array<string, mixed>|null */
    public function findConfigByUsuario(int $usuarioId, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT bc.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    f.nombre AS firma_nombre, f.slug AS firma_slug, f.timezone AS firma_timezone
             FROM booking_configs bc
             INNER JOIN usuarios u ON u.id = bc.usuario_id AND u.firma_id = bc.firma_id
             INNER JOIN firmas f ON f.id = bc.firma_id
             WHERE bc.usuario_id = :usuario_id AND bc.firma_id = :firma_id
               AND bc.deleted_at IS NULL'
        );
        $statement->execute(['usuario_id' => $usuarioId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findConfigById(int $id, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT bc.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    f.nombre AS firma_nombre, f.slug AS firma_slug, f.timezone AS firma_timezone
             FROM booking_configs bc
             INNER JOIN usuarios u ON u.id = bc.usuario_id AND u.firma_id = bc.firma_id
             INNER JOIN firmas f ON f.id = bc.firma_id
             WHERE bc.id = :id AND bc.firma_id = :firma_id AND bc.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function findConfigsForFirma(int $firmaId): array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT bc.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    (SELECT COUNT(*) FROM booking_appointments ba
                     WHERE ba.booking_config_id = bc.id AND ba.firma_id = bc.firma_id) AS total_citas
             FROM booking_configs bc
             INNER JOIN usuarios u ON u.id = bc.usuario_id AND u.firma_id = bc.firma_id
             WHERE bc.firma_id = :firma_id AND bc.deleted_at IS NULL
             ORDER BY u.nombre, bc.id'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        // PUBLIC SLUG EXCEPTION: slugs are globally unique by schema.
        $sql = 'SELECT COUNT(*) FROM booking_configs WHERE slug = :slug AND deleted_at IS NULL';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function createConfig(array $data): int
    {
        // TENANT FILTER: firma_id is mandatory in the inserted config.
        $statement = $this->pdo->prepare(
            'INSERT INTO booking_configs
                (firma_id, usuario_id, slug, titulo, descripcion, duracion_minutos, dias_activos,
                 hora_inicio, hora_fin, buffer_entre_citas, dias_anticipacion_min,
                 dias_anticipacion_max, activo, notificar_email, created_at, updated_at)
             VALUES
                (:firma_id, :usuario_id, :slug, :titulo, :descripcion, :duracion_minutos, :dias_activos,
                 :hora_inicio, :hora_fin, :buffer_entre_citas, :dias_anticipacion_min,
                 :dias_anticipacion_max, :activo, :notificar_email, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateConfig(int $id, int $firmaId, array $data): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE booking_configs
             SET titulo = :titulo, descripcion = :descripcion, duracion_minutos = :duracion_minutos,
                 dias_activos = :dias_activos, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
                 buffer_entre_citas = :buffer_entre_citas,
                 dias_anticipacion_min = :dias_anticipacion_min,
                 dias_anticipacion_max = :dias_anticipacion_max, activo = :activo,
                 notificar_email = :notificar_email, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    public function softDeleteConfig(int $id, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE booking_configs
             SET deleted_at = CURRENT_TIMESTAMP, activo = 0, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    /**
     * PUBLIC AVAILABILITY EXCEPTION: configId was resolved from the public slug.
     *
     * @return list<string>
     */
    public function getOccupiedSlots(int $configId, string $fecha): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ba.hora_inicio
             FROM booking_appointments ba
             INNER JOIN booking_configs bc
                ON bc.id = ba.booking_config_id AND bc.firma_id = ba.firma_id
             WHERE ba.booking_config_id = :config_id AND ba.fecha = :fecha
               AND ba.estado = \'confirmada\' AND bc.deleted_at IS NULL
             ORDER BY ba.hora_inicio'
        );
        $statement->execute(['config_id' => $configId, 'fecha' => $fecha]);

        return array_map(
            static fn (mixed $value): string => substr((string) $value, 0, 5),
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /** @param array<string, mixed> $data */
    public function createAppointment(array $data): int
    {
        // TENANT FILTER: firma_id is mandatory in the inserted appointment.
        $statement = $this->pdo->prepare(
            'INSERT INTO booking_appointments
                (firma_id, booking_config_id, usuario_id, nombre_cliente, email_cliente,
                 telefono_cliente, fecha, hora_inicio, hora_fin, notas, estado, prospecto_id,
                 token_cancelacion, created_at, updated_at)
             VALUES
                (:firma_id, :booking_config_id, :usuario_id, :nombre_cliente, :email_cliente,
                 :telefono_cliente, :fecha, :hora_inicio, :hora_fin, :notas, \'confirmada\', :prospecto_id,
                 :token_cancelacion, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function findAppointments(int $firmaId, array $filters): array
    {
        // TENANT FILTER
        $where = ['ba.firma_id = :firma_id'];
        $params = ['firma_id' => $firmaId];
        foreach (['booking_config_id', 'usuario_id'] as $field) {
            $value = filter_var($filters[$field] ?? null, FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $where[] = 'ba.' . $field . ' = :' . $field;
                $params[$field] = $value;
            }
        }
        foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $field => $operator) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = 'ba.fecha ' . $operator . ' :' . $field;
                $params[$field] = $value;
            }
        }
        $status = (string) ($filters['estado'] ?? '');
        if (in_array($status, ['confirmada', 'cancelada', 'completada'], true)) {
            $where[] = 'ba.estado = :estado';
            $params['estado'] = $status;
        }

        $statement = $this->pdo->prepare(
            'SELECT ba.*, bc.titulo AS booking_titulo, u.nombre AS usuario_nombre,
                    p.nombre AS prospecto_nombre
             FROM booking_appointments ba
             INNER JOIN booking_configs bc ON bc.id = ba.booking_config_id AND bc.firma_id = ba.firma_id
             INNER JOIN usuarios u ON u.id = ba.usuario_id AND u.firma_id = ba.firma_id
             LEFT JOIN prospectos p ON p.id = ba.prospecto_id AND p.firma_id = ba.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ba.fecha DESC, ba.hora_inicio DESC, ba.id DESC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * PUBLIC TOKEN EXCEPTION: the cryptographically random token is the locator.
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ba.*, bc.titulo AS booking_titulo, u.nombre AS usuario_nombre,
                    f.nombre AS firma_nombre, f.timezone AS firma_timezone
             FROM booking_appointments ba
             INNER JOIN booking_configs bc ON bc.id = ba.booking_config_id AND bc.firma_id = ba.firma_id
             INNER JOIN usuarios u ON u.id = ba.usuario_id AND u.firma_id = ba.firma_id
             INNER JOIN firmas f ON f.id = ba.firma_id
             WHERE ba.token_cancelacion = :token'
        );
        $statement->execute(['token' => $token]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** PUBLIC TOKEN EXCEPTION: cancellation is authorized by the secret token. */
    public function cancelByToken(string $token): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE booking_appointments
             SET estado = \'cancelada\', updated_at = CURRENT_TIMESTAMP
             WHERE token_cancelacion = :token AND estado = \'confirmada\''
        );
        $statement->execute(['token' => $token]);

        return $statement->rowCount() > 0;
    }

    public function completeAppointment(int $id, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE booking_appointments
             SET estado = \'completada\', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND estado = \'confirmada\''
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    private function configBySlug(string $slug, bool $forUpdate): ?array
    {
        // PUBLIC SLUG EXCEPTION: slug resolves the tenant. FOR UPDATE serializes bookings.
        $suffix = $forUpdate && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' FOR UPDATE'
            : '';
        $statement = $this->pdo->prepare(
            'SELECT bc.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                    f.nombre AS firma_nombre, f.slug AS firma_slug, f.timezone AS firma_timezone,
                    f.estado AS firma_estado
             FROM booking_configs bc
             INNER JOIN usuarios u ON u.id = bc.usuario_id AND u.firma_id = bc.firma_id
             INNER JOIN firmas f ON f.id = bc.firma_id
             WHERE bc.slug = :slug AND bc.deleted_at IS NULL
               AND u.deleted_at IS NULL AND f.deleted_at IS NULL' . $suffix
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
