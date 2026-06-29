<?php

declare(strict_types=1);

namespace App\Repositories;

final class RolRepository extends BaseRepository
{
    private const ASSIGNABLE_PERMISSION_FILTER = 'modulo NOT IN (\'firmas\',\'planes\',\'limites\',\'checklist\')
               AND codigo NOT IN (\'legal.administrar\',\'soporte.ver_global\')';

    /** @return list<array<string, mixed>> */
    public function allForFirma(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.id,r.codigo,r.nombre,r.descripcion,r.estado,r.is_protected,COUNT(DISTINCT ur.usuario_id) AS usuarios
             FROM roles r LEFT JOIN usuario_roles ur ON ur.rol_id=r.id AND ur.firma_id=r.firma_id
             WHERE r.firma_id=:firma_id AND r.deleted_at IS NULL
             GROUP BY r.id,r.codigo,r.nombre,r.descripcion,r.estado,r.is_protected ORDER BY r.nombre'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM roles WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(int $firmaId, string $code): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM roles WHERE firma_id=:firma_id AND codigo=:codigo AND deleted_at IS NULL');
        $statement->execute(['firma_id' => $firmaId, 'codigo' => $code]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO roles (firma_id,codigo,nombre,descripcion,estado,is_protected,created_at,updated_at)
             VALUES (:firma_id,:codigo,:nombre,:descripcion,:estado,:is_protected,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data + ['firma_id' => $firmaId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE roles SET codigo=:codigo,nombre=:nombre,descripcion=:descripcion,estado=:estado,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    /** @return list<array<string, mixed>> */
    public function permissions(): array
    {
        return $this->pdo->query(
            'SELECT id,codigo,modulo,accion,descripcion
             FROM permisos
             WHERE ' . self::ASSIGNABLE_PERMISSION_FILTER . '
             ORDER BY modulo,accion'
        )->fetchAll();
    }

    /** @param list<int> $permissionIds @return list<int> */
    public function assignablePermissionIds(array $permissionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM permisos
             WHERE id IN (' . $placeholders . ')
               AND ' . self::ASSIGNABLE_PERMISSION_FILTER
        );
        $statement->execute($ids);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return list<int> */
    public function permissionIds(int $firmaId, int $roleId): array
    {
        $statement = $this->pdo->prepare('SELECT permiso_id FROM rol_permiso WHERE firma_id=:firma_id AND rol_id=:rol_id ORDER BY permiso_id');
        $statement->execute(['firma_id' => $firmaId, 'rol_id' => $roleId]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @param list<int> $permissionIds */
    public function syncPermissions(int $firmaId, int $roleId, array $permissionIds): void
    {
        $delete = $this->pdo->prepare('DELETE FROM rol_permiso WHERE firma_id=:firma_id AND rol_id=:rol_id');
        $delete->execute(['firma_id' => $firmaId, 'rol_id' => $roleId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO rol_permiso (firma_id,rol_id,permiso_id,created_at)
             SELECT :firma_id,:rol_id,p.id,CURRENT_TIMESTAMP(6) FROM permisos p WHERE p.id=:permiso_id'
        );
        foreach (array_unique($permissionIds) as $permissionId) {
            $insert->execute(['firma_id' => $firmaId, 'rol_id' => $roleId, 'permiso_id' => $permissionId]);
        }
    }

    /** @param list<int> $roleIds */
    public function syncUserRoles(int $firmaId, int $userId, array $roleIds): void
    {
        $delete = $this->pdo->prepare('DELETE FROM usuario_roles WHERE firma_id=:firma_id AND usuario_id=:usuario_id');
        $delete->execute(['firma_id' => $firmaId, 'usuario_id' => $userId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO usuario_roles (firma_id,usuario_id,rol_id,created_at)
             SELECT :firma_id,:usuario_id,r.id,CURRENT_TIMESTAMP(6) FROM roles r
             WHERE r.id=:rol_id AND r.firma_id=:firma_check AND r.estado=\'activo\' AND r.deleted_at IS NULL'
        );
        foreach (array_unique($roleIds) as $roleId) {
            $insert->execute(['firma_id' => $firmaId, 'usuario_id' => $userId, 'rol_id' => $roleId, 'firma_check' => $firmaId]);
        }
    }
}
