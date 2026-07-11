<?php

declare(strict_types=1);

namespace App\Repositories;

final class PerfilRepository extends BaseRepository
{
    /** @return array<string, mixed>|null */
    public function findOwn(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                u.id,
                u.firma_id,
                u.nombre,
                u.nombres,
                u.apellidos,
                u.tipo_documento_id,
                ci.codigo AS tipo_documento_codigo,
                ci.etiqueta AS tipo_documento_etiqueta,
                u.numero_documento,
                u.numero_documento_normalizado,
                u.telefono,
                u.cargo,
                u.foto_perfil_path,
                u.es_abogado,
                u.tiene_tarjeta_profesional,
                u.numero_tarjeta_profesional,
                u.numero_tarjeta_profesional_normalizado,
                u.tarjeta_profesional_verificacion_estado,
                u.fecha_verificacion_tarjeta,
                u.usuario_verificador_tarjeta_id,
                u.observacion_verificacion_tarjeta,
                u.email,
                u.tipo,
                u.estado,
                u.last_login_at,
                u.created_at
             FROM usuarios u
             LEFT JOIN catalogo_items ci ON ci.id = u.tipo_documento_id
             WHERE u.id = :id
               AND u.deleted_at IS NULL'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array{id: int|string, nombre: string}> */
    public function roles(int $userId, ?int $firmaId): array
    {
        if ($firmaId === null) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT r.id, r.nombre
             FROM usuario_roles ur
             INNER JOIN roles r
                ON r.id = ur.rol_id
               AND r.firma_id = ur.firma_id
             WHERE ur.usuario_id = :usuario_id
               AND ur.firma_id = :firma_id
               AND r.deleted_at IS NULL
             ORDER BY r.nombre'
        );
        $statement->execute(['usuario_id' => $userId, 'firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return list<array{id: int|string, codigo: string, etiqueta: string}> */
    public function documentTypes(): array
    {
        $statement = $this->pdo->query(
            'SELECT ci.id, ci.codigo, ci.etiqueta
             FROM catalogo_items ci
             INNER JOIN catalogos c ON c.id = ci.catalogo_id
             WHERE c.scope_key = \'global:tipo_documento\'
               AND c.firma_id IS NULL
               AND ci.firma_id IS NULL
               AND c.estado = \'activo\'
               AND ci.estado = \'activo\'
               AND c.deleted_at IS NULL
               AND ci.deleted_at IS NULL
             ORDER BY ci.orden, ci.etiqueta'
        );

        return $statement->fetchAll();
    }

    public function documentTypeIsAllowed(int $documentTypeId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM catalogo_items ci
             INNER JOIN catalogos c ON c.id = ci.catalogo_id
             WHERE ci.id = :id
               AND c.scope_key = \'global:tipo_documento\'
               AND c.firma_id IS NULL
               AND ci.firma_id IS NULL
               AND c.estado = \'activo\'
               AND ci.estado = \'activo\'
               AND c.deleted_at IS NULL
               AND ci.deleted_at IS NULL'
        );
        $statement->execute(['id' => $documentTypeId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function documentExists(
        ?int $firmaId,
        int $documentTypeId,
        string $normalizedDocument,
        int $excludeUserId
    ): bool {
        $sql = 'SELECT COUNT(*)
                FROM usuarios
                WHERE tipo_documento_id = :tipo_documento_id
                  AND numero_documento_normalizado = :numero_documento_normalizado
                  AND id <> :id
                  AND deleted_at IS NULL';
        $params = [
            'tipo_documento_id' => $documentTypeId,
            'numero_documento_normalizado' => $normalizedDocument,
            'id' => $excludeUserId,
        ];

        if ($firmaId === null) {
            $sql .= ' AND firma_id IS NULL';
        } else {
            $sql .= ' AND firma_id = :firma_id';
            $params['firma_id'] = $firmaId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function updatePersonal(int $userId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET nombre = :nombre,
                 nombres = :nombres,
                 apellidos = :apellidos,
                 tipo_documento_id = :tipo_documento_id,
                 numero_documento = :numero_documento,
                 numero_documento_normalizado = :numero_documento_normalizado,
                 telefono = :telefono,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $userId]);
    }

    public function updatePhotoPath(int $userId, ?string $path): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET foto_perfil_path = :foto_perfil_path,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute(['foto_perfil_path' => $path, 'id' => $userId]);
    }

    /** @param array<string, mixed> $data */
    public function updateProfessional(int $userId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET es_abogado = :es_abogado,
                 tiene_tarjeta_profesional = :tiene_tarjeta_profesional,
                 numero_tarjeta_profesional = :numero_tarjeta_profesional,
                 numero_tarjeta_profesional_normalizado = :numero_tarjeta_profesional_normalizado,
                 tarjeta_profesional_verificacion_estado = :tarjeta_profesional_verificacion_estado,
                 fecha_verificacion_tarjeta = NULL,
                 usuario_verificador_tarjeta_id = NULL,
                 observacion_verificacion_tarjeta = NULL,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $userId]);
    }

    public function findPasswordHash(int $userId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT password_hash
             FROM usuarios
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? ((string) $row['password_hash']) : null;
    }

    public function updatePasswordHash(int $userId, string $hash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET password_hash = :hash,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute(['hash' => $hash, 'id' => $userId]);
    }

    /** @param array<string, mixed> $data */
    public function recordSensitiveChange(array $data): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO usuario_cambios_sensibles
                (firma_id, usuario_afectado_id, usuario_actor_id, campo,
                 valor_anterior_enmascarado, valor_nuevo_enmascarado,
                 valor_anterior_hash, valor_nuevo_hash, origen, ip_address, user_agent)
             VALUES
                (:firma_id, :usuario_afectado_id, :usuario_actor_id, :campo,
                 :valor_anterior_enmascarado, :valor_nuevo_enmascarado,
                 :valor_anterior_hash, :valor_nuevo_hash, :origen, :ip_address, :user_agent)'
        );
        $statement->execute($data);
    }
}
