<?php

declare(strict_types=1);

namespace App\Repositories;

final class SuperadminRolRepository extends BaseRepository
{
    /** Módulos de la plataforma que pueden asignarse en roles superadmin. */
    private const SA_MODULES = [
        'firmas', 'planes', 'limites', 'auditoria', 'configuracion',
        'legal', 'soporte', 'checklist',
        'superadmin_usuarios', 'superadmin_roles', 'sistema',
    ];

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        return $this->pdo->query(
            "SELECT r.id, r.codigo, r.nombre, r.descripcion, r.estado, r.is_protected,
                    COUNT(DISTINCT rp.permiso_id) AS total_permisos,
                    COUNT(DISTINCT ur.usuario_id) AS total_usuarios
             FROM roles r
             LEFT JOIN rol_permiso rp  ON rp.rol_id = r.id AND rp.firma_id IS NULL
             LEFT JOIN usuario_roles ur ON ur.rol_id = r.id AND ur.firma_id IS NULL
             WHERE r.firma_id IS NULL AND r.deleted_at IS NULL
             GROUP BY r.id, r.codigo, r.nombre, r.descripcion, r.estado, r.is_protected
             ORDER BY r.nombre"
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        $stmt = $this->pdo->prepare(
            "SELECT id, codigo, nombre, descripcion, estado, is_protected
             FROM roles
             WHERE id = :id AND firma_id IS NULL AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM roles WHERE codigo = :code AND firma_id IS NULL AND deleted_at IS NULL";
        $params = ['code' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO roles (firma_id, codigo, nombre, descripcion, estado, is_protected, created_at, updated_at)
             VALUES (NULL, :codigo, :nombre, :descripcion, :estado, 0, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE roles
             SET nombre = :nombre, descripcion = :descripcion, estado = :estado,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND firma_id IS NULL AND deleted_at IS NULL"
        );
        $stmt->execute($data + ['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function superadminPermissions(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::SA_MODULES), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, codigo, modulo, accion, descripcion
             FROM permisos
             WHERE modulo IN ({$placeholders})
             ORDER BY modulo, accion"
        );
        $stmt->execute(self::SA_MODULES);

        return $stmt->fetchAll();
    }

    /** @return list<int> */
    public function permissionIds(int $roleId): array
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        $stmt = $this->pdo->prepare(
            'SELECT permiso_id FROM rol_permiso
             WHERE rol_id = :rol_id AND firma_id IS NULL
             ORDER BY permiso_id'
        );
        $stmt->execute(['rol_id' => $roleId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @param list<int> $permissionIds */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        $del = $this->pdo->prepare(
            'DELETE FROM rol_permiso WHERE rol_id = :rol_id AND firma_id IS NULL'
        );
        $del->execute(['rol_id' => $roleId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
             SELECT NULL, :rol_id, p.id, CURRENT_TIMESTAMP(6)
             FROM permisos p WHERE p.id = :permiso_id'
        );
        foreach (array_unique($permissionIds) as $pid) {
            $ins->execute(['rol_id' => $roleId, 'permiso_id' => $pid]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function usersForRole(int $roleId): array
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.nombre, u.email, u.cargo, u.estado
             FROM usuario_roles ur
             INNER JOIN usuarios u ON u.id = ur.usuario_id
             WHERE ur.rol_id = :rol_id AND ur.firma_id IS NULL
               AND u.tipo = 'superadmin' AND u.firma_id IS NULL AND u.deleted_at IS NULL
             ORDER BY u.nombre"
        );
        $stmt->execute(['rol_id' => $roleId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allSuperadminUsers(): array
    {
        // SUPERADMIN FILTER: tipo='superadmin' AND firma_id IS NULL
        return $this->pdo->query(
            "SELECT id, nombre, email FROM usuarios
             WHERE tipo = 'superadmin' AND firma_id IS NULL AND estado = 'activo' AND deleted_at IS NULL
             ORDER BY nombre"
        )->fetchAll();
    }

    /** @return list<int> */
    public function userRoleIds(int $userId): array
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        $stmt = $this->pdo->prepare(
            'SELECT rol_id FROM usuario_roles
             WHERE usuario_id = :uid AND firma_id IS NULL'
        );
        $stmt->execute(['uid' => $userId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function assignToUser(int $userId, int $roleId): void
    {
        // Check first to avoid duplicate (NULL != NULL in UNIQUE constraints)
        $check = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usuario_roles
             WHERE firma_id IS NULL AND usuario_id = :uid AND rol_id = :rid'
        );
        $check->execute(['uid' => $userId, 'rid' => $roleId]);
        if ((int) $check->fetchColumn() > 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario_roles (firma_id, usuario_id, rol_id, created_at)
             VALUES (NULL, :uid, :rid, CURRENT_TIMESTAMP(6))'
        );
        $stmt->execute(['uid' => $userId, 'rid' => $roleId]);
    }

    public function removeFromUser(int $userId, int $roleId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM usuario_roles
             WHERE firma_id IS NULL AND usuario_id = :uid AND rol_id = :rid'
        );
        $stmt->execute(['uid' => $userId, 'rid' => $roleId]);
    }

    /** @param list<int> $roleIds */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $del = $this->pdo->prepare(
            'DELETE FROM usuario_roles WHERE firma_id IS NULL AND usuario_id = :uid'
        );
        $del->execute(['uid' => $userId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO usuario_roles (firma_id, usuario_id, rol_id, created_at)
             VALUES (NULL, :uid, :rid, CURRENT_TIMESTAMP(6))'
        );
        foreach (array_unique($roleIds) as $rid) {
            $ins->execute(['uid' => $userId, 'rid' => $rid]);
        }
    }

    public function count(): int
    {
        // SUPERADMIN FILTER: firma_id IS NULL
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM roles WHERE firma_id IS NULL AND deleted_at IS NULL"
        )->fetchColumn();
    }
}
