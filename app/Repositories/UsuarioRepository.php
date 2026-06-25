<?php

declare(strict_types=1);

namespace App\Repositories;

final class UsuarioRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function allForFirma(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id,u.nombre,u.email,u.tipo,u.estado,u.last_login_at,u.created_at,
                    GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR \', \') AS roles
             FROM usuarios u
             LEFT JOIN usuario_roles ur ON ur.usuario_id=u.id AND ur.firma_id=u.firma_id
             LEFT JOIN roles r ON r.id=ur.rol_id AND r.firma_id=ur.firma_id
             WHERE u.firma_id=:firma_id AND u.deleted_at IS NULL
             GROUP BY u.id,u.nombre,u.email,u.tipo,u.estado,u.last_login_at,u.created_at
             ORDER BY u.nombre'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM usuarios WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.*, f.slug AS firma_slug, f.estado AS firma_estado
             FROM usuarios u LEFT JOIN firmas f ON f.id=u.firma_id
             WHERE u.id=:id AND u.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function findLoginCandidates(string $email, ?string $firmaSlug): array
    {
        $sql = 'SELECT u.*, f.slug AS firma_slug, f.estado AS firma_estado
                FROM usuarios u LEFT JOIN firmas f ON f.id=u.firma_id
                WHERE u.email_normalizado=:email AND u.deleted_at IS NULL';
        $params = ['email' => $email];
        if ($firmaSlug !== null && $firmaSlug !== '') {
            $sql .= ' AND f.slug=:firma_slug';
            $params['firma_slug'] = $firmaSlug;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY u.id');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function emailScopeExists(string $scope, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE email_scope=:scope AND deleted_at IS NULL';
        $params = ['scope' => $scope];
        if ($excludeId !== null) {
            $sql .= ' AND id<>:id';
            $params['id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO usuarios
            (firma_id,nombre,email,email_normalizado,email_scope,password_hash,tipo,estado,must_change_password,invited_at,created_at,updated_at)
            VALUES
            (:firma_id,:nombre,:email,:email_normalizado,:email_scope,:password_hash,:tipo,\'activo\',:must_change_password,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios SET nombre=:nombre,email=:email,email_normalizado=:email_normalizado,email_scope=:email_scope,tipo=:tipo,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function setStatus(int $firmaId, int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios SET estado=:estado,deactivated_at=CASE WHEN :status_check=\'inactivo\' THEN CURRENT_TIMESTAMP(6) ELSE NULL END,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['estado' => $status, 'status_check' => $status, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function updateLastLogin(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE usuarios SET last_login_at=CURRENT_TIMESTAMP(6) WHERE id=:id');
        $statement->execute(['id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios SET password_hash=:password_hash,must_change_password=0,updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND deleted_at IS NULL'
        );
        $statement->execute(['password_hash' => $passwordHash, 'id' => $id]);
    }

    /** @return list<string> */
    public function permissionCodes(int $userId, int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT p.codigo FROM usuario_roles ur
             INNER JOIN rol_permiso rp ON rp.rol_id=ur.rol_id AND rp.firma_id=ur.firma_id
             INNER JOIN permisos p ON p.id=rp.permiso_id
             WHERE ur.usuario_id=:usuario_id AND ur.firma_id=:firma_id ORDER BY p.codigo'
        );
        $statement->execute(['usuario_id' => $userId, 'firma_id' => $firmaId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return list<array<string, mixed>> */
    public function roles(int $userId, int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.id,r.codigo,r.nombre FROM usuario_roles ur
             INNER JOIN roles r ON r.id=ur.rol_id AND r.firma_id=ur.firma_id
             WHERE ur.usuario_id=:usuario_id AND ur.firma_id=:firma_id AND r.deleted_at IS NULL ORDER BY r.nombre'
        );
        $statement->execute(['usuario_id' => $userId, 'firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    public function hasRole(int $userId, int $firmaId, string $roleCode): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usuario_roles ur INNER JOIN roles r ON r.id=ur.rol_id AND r.firma_id=ur.firma_id
             WHERE ur.usuario_id=:usuario_id AND ur.firma_id=:firma_id AND r.codigo=:codigo AND r.deleted_at IS NULL'
        );
        $statement->execute(['usuario_id' => $userId, 'firma_id' => $firmaId, 'codigo' => $roleCode]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function countActiveAdmins(int $firmaId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT u.id) FROM usuarios u
             INNER JOIN usuario_roles ur ON ur.usuario_id=u.id AND ur.firma_id=u.firma_id
             INNER JOIN roles r ON r.id=ur.rol_id AND r.firma_id=ur.firma_id AND r.codigo=\'administrador\'
             WHERE u.firma_id=:firma_id AND u.estado=\'activo\' AND u.deleted_at IS NULL'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return (int) $statement->fetchColumn();
    }
}
