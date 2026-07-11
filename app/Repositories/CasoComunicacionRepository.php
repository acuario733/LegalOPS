<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CasoComunicacionRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function findByCaso(int $casoId, int $firmaId, array $filters = []): array
    {
        // TENANT FILTER
        $where = ['cc.caso_id = :caso_id', 'cc.firma_id = :firma_id', 'cc.deleted_at IS NULL'];
        $params = ['caso_id' => $casoId, 'firma_id' => $firmaId];

        foreach (['tipo', 'direccion'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $where[] = 'cc.' . $field . ' = :' . $field;
                $params[$field] = (string) $filters[$field];
            }
        }
        if (($filters['fecha_desde'] ?? '') !== '') {
            $where[] = 'cc.fecha_comunicacion >= :fecha_desde';
            $params['fecha_desde'] = (string) $filters['fecha_desde'] . ' 00:00:00';
        }
        if (($filters['fecha_hasta'] ?? '') !== '') {
            $where[] = 'cc.fecha_comunicacion <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filters['fecha_hasta'] . ' 23:59:59';
        }

        $statement = $this->pdo->prepare(
            'SELECT cc.*, u.nombre AS usuario_nombre
             FROM caso_comunicaciones cc
             INNER JOIN usuarios u ON u.id = cc.usuario_id AND u.firma_id = cc.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cc.fecha_comunicacion DESC, cc.id DESC'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM caso_comunicaciones
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO caso_comunicaciones
                (firma_id, caso_id, tipo, direccion, asunto, cuerpo, participantes,
                 fecha_comunicacion, duracion_minutos, adjuntos, usuario_id, origen,
                 email_message_id, created_at, updated_at)
             VALUES
                (:firma_id, :caso_id, :tipo, :direccion, :asunto, :cuerpo, :participantes,
                 :fecha_comunicacion, :duracion_minutos, :adjuntos, :usuario_id, :origen,
                 :email_message_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function softDelete(int $id, int $firmaId): bool
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'UPDATE caso_comunicaciones
             SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    public function markRead(int $id, int $casoId, int $firmaId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO comunicacion_lecturas (comunicacion_id,firma_id,usuario_id,leido_at)
             SELECT id,firma_id,:usuario_id,CURRENT_TIMESTAMP FROM caso_comunicaciones
             WHERE id=:id AND caso_id=:caso_id AND firma_id=:firma_id
               AND direccion=\'entrante\' AND deleted_at IS NULL
             ON DUPLICATE KEY UPDATE leido_at=VALUES(leido_at)'
        );
        $statement->execute(['usuario_id' => $userId, 'id' => $id, 'caso_id' => $casoId, 'firma_id' => $firmaId]);

        return $statement->rowCount() > 0;
    }

    public function unreadCount(int $firmaId, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM caso_comunicaciones cc
             LEFT JOIN comunicacion_lecturas cl
               ON cl.comunicacion_id=cc.id AND cl.firma_id=cc.firma_id AND cl.usuario_id=:usuario_id
             WHERE cc.firma_id=:firma_id AND cc.direccion=\'entrante\' AND cc.deleted_at IS NULL
               AND cl.comunicacion_id IS NULL'
        );
        $statement->execute(['usuario_id' => $userId, 'firma_id' => $firmaId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findByEmailMessageId(string $messageId): ?array
    {
        // PUBLIC INBOUND DEDUPE EXCEPTION: message-id is globally unique and tenant is unknown before token resolution.
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM caso_comunicaciones
             WHERE email_message_id = :message_id
             LIMIT 1'
        );
        $statement->execute(['message_id' => $messageId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function getOrCreateEmailAddress(int $casoId, int $firmaId, string $dominio): string
    {
        // TENANT FILTER
        $existing = $this->emailAddressByCaso($casoId, $firmaId);
        if ($existing !== null) {
            return (string) $existing['email_address'];
        }

        $domain = strtolower(trim($dominio));
        do {
            $token = bin2hex(random_bytes(16));
            $email = 'caso-' . $token . '@' . $domain;
        } while ($this->emailTokenExists($token));

        $statement = $this->pdo->prepare(
            'INSERT INTO caso_email_addresses
                (caso_id, firma_id, email_address, token, activo, created_at)
             VALUES
                (:caso_id, :firma_id, :email_address, :token, 1, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'caso_id' => $casoId,
            'firma_id' => $firmaId,
            'email_address' => $email,
            'token' => $token,
        ]);

        return $email;
    }

    /** @return array<string, mixed>|null */
    public function findEmailAddressByToken(string $token): ?array
    {
        // PUBLIC INBOUND TOKEN EXCEPTION: token resolves the tenant and case.
        $statement = $this->pdo->prepare(
            'SELECT cea.*, c.responsable_usuario_id
             FROM caso_email_addresses cea
             INNER JOIN casos c ON c.id = cea.caso_id AND c.firma_id = cea.firma_id
             WHERE cea.token = :token
             LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function emailAddressByCaso(int $casoId, int $firmaId): ?array
    {
        // TENANT FILTER
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM caso_email_addresses
             WHERE caso_id = :caso_id AND firma_id = :firma_id AND activo = 1
             ORDER BY id ASC
             LIMIT 1'
        );
        $statement->execute(['caso_id' => $casoId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function emailTokenExists(string $token): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM caso_email_addresses WHERE token = :token');
        $statement->execute(['token' => $token]);

        return (int) $statement->fetchColumn() > 0;
    }
}
