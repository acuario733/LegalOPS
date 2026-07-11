<?php

declare(strict_types=1);

namespace App\Repositories;

final class SuperadminUsuarioRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        // SUPERADMIN FILTER: tipo='superadmin' AND firma_id IS NULL
        return $this->pdo->query(
            "SELECT id, nombre, email, cargo, estado, last_login_at, created_at
             FROM usuarios
             WHERE tipo='superadmin' AND firma_id IS NULL AND deleted_at IS NULL
             ORDER BY nombre"
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        // SUPERADMIN FILTER: tipo='superadmin' AND firma_id IS NULL
        $stmt = $this->pdo->prepare(
            "SELECT id, nombre, email, cargo, estado, last_login_at, created_at, must_change_password
             FROM usuarios
             WHERE id = :id AND tipo = 'superadmin' AND firma_id IS NULL AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function emailScopeExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM usuarios WHERE email_scope = :email AND deleted_at IS NULL';
        $params = ['email' => $email];
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
            "INSERT INTO usuarios
             (firma_id, nombre, email, email_normalizado, email_scope, password_hash, cargo,
              tipo, estado, must_change_password, created_at, updated_at)
             VALUES
             (NULL, :nombre, :email, :email_normalizado, :email_scope, :password_hash, :cargo,
              'superadmin', 'activo', 1, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))"
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateProfile(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET nombre            = :nombre,
                 email             = :email,
                 email_normalizado = :email_normalizado,
                 email_scope       = :email_scope,
                 cargo             = :cargo,
                 updated_at        = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND tipo = 'superadmin' AND firma_id IS NULL AND deleted_at IS NULL"
        );
        $stmt->execute($data + ['id' => $id]);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET estado         = 'inactivo',
                 deactivated_at = CURRENT_TIMESTAMP(6),
                 updated_at     = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND tipo = 'superadmin' AND firma_id IS NULL"
        );
        $stmt->execute(['id' => $id]);
    }

    public function reactivate(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET estado         = 'activo',
                 deactivated_at = NULL,
                 updated_at     = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND tipo = 'superadmin' AND firma_id IS NULL"
        );
        $stmt->execute(['id' => $id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET password_hash        = :hash,
                 must_change_password = 1,
                 updated_at           = CURRENT_TIMESTAMP(6)
             WHERE id = :id AND tipo = 'superadmin' AND firma_id IS NULL"
        );
        $stmt->execute(['id' => $id, 'hash' => $hash]);
    }

    public function countActive(): int
    {
        // SUPERADMIN FILTER: tipo='superadmin' AND firma_id IS NULL
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM usuarios
             WHERE tipo = 'superadmin' AND firma_id IS NULL AND estado = 'activo' AND deleted_at IS NULL"
        )->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function lastLogin(?int $excludeId = null): ?array
    {
        // SUPERADMIN FILTER: tipo='superadmin' AND firma_id IS NULL
        $sql    = "SELECT nombre, last_login_at FROM usuarios
                   WHERE tipo = 'superadmin' AND firma_id IS NULL
                     AND last_login_at IS NOT NULL AND deleted_at IS NULL";
        $params = [];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY last_login_at DESC LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
