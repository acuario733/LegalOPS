<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserSessionRepository extends BaseRepository
{
    public function register(int $userId, ?int $firmaId, string $sessionId, string $fingerprint, string $ip, string $userAgent, int $lifetimeMinutes): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_sessions
            (firma_id,usuario_id,session_hash,fingerprint_hash,ip_address,user_agent,last_activity_at,expires_at,created_at)
            VALUES
            (:firma_id,:usuario_id,:session_hash,:fingerprint_hash,:ip_address,:user_agent,CURRENT_TIMESTAMP(6),:expires_at,CURRENT_TIMESTAMP(6))'
        );
        $statement->bindValue(':firma_id', $firmaId, $firmaId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $statement->bindValue(':usuario_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':session_hash', hash('sha256', $sessionId));
        $statement->bindValue(':fingerprint_hash', hash('sha256', $fingerprint));
        $statement->bindValue(':ip_address', $ip);
        $statement->bindValue(':user_agent', mb_substr($userAgent, 0, 255));
        $statement->bindValue(':expires_at', (new \DateTimeImmutable())->modify('+' . max(1, $lifetimeMinutes) . ' minutes')->format('Y-m-d H:i:s.u'));
        $statement->execute();
    }

    public function isActive(int $userId, string $sessionId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM user_sessions
             WHERE usuario_id=:usuario_id AND session_hash=:session_hash AND revoked_at IS NULL AND expires_at>CURRENT_TIMESTAMP(6)
             LIMIT 1'
        );
        $statement->execute(['usuario_id' => $userId, 'session_hash' => hash('sha256', $sessionId)]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            return false;
        }
        $touch = $this->pdo->prepare('UPDATE user_sessions SET last_activity_at=CURRENT_TIMESTAMP(6) WHERE id=:id');
        $touch->execute(['id' => $id]);

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function allForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,ip_address,user_agent,last_activity_at,expires_at,revoked_at,revoked_reason,created_at
             FROM user_sessions WHERE usuario_id=:usuario_id ORDER BY created_at DESC'
        );
        $statement->execute(['usuario_id' => $userId]);

        return $statement->fetchAll();
    }

    public function revoke(int $sessionId, int $userId, ?int $firmaId, string $reason, bool $administrative = false): bool
    {
        $sql = 'UPDATE user_sessions SET revoked_at=CURRENT_TIMESTAMP(6),revoked_reason=:reason
                WHERE id=:id AND revoked_at IS NULL';
        $params = ['reason' => $reason, 'id' => $sessionId];
        if ($administrative && $firmaId !== null) {
            $sql .= ' AND firma_id=:firma_id';
            $params['firma_id'] = $firmaId;
        } else {
            $sql .= ' AND usuario_id=:usuario_id';
            $params['usuario_id'] = $userId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }

    public function revokeCurrent(int $userId, string $sessionId, string $reason): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at=CURRENT_TIMESTAMP(6),revoked_reason=:reason
             WHERE usuario_id=:usuario_id AND session_hash=:session_hash AND revoked_at IS NULL'
        );
        $statement->execute(['reason' => $reason, 'usuario_id' => $userId, 'session_hash' => hash('sha256', $sessionId)]);
    }

    public function revokeAllForUser(int $userId, string $reason): void
    {
        // Nota (Sesion 7, 2026-07-11): timestamp calculado en PHP en vez de
        // CURRENT_TIMESTAMP(6) para compatibilidad con SQLite en pruebas
        // unitarias (antes de esta sesion este metodo nunca habia sido probado).
        // Mismo valor efectivo en MySQL.
        $statement = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at=:revoked_at,revoked_reason=:reason
             WHERE usuario_id=:usuario_id AND revoked_at IS NULL'
        );
        $statement->execute([
            'revoked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'reason' => $reason,
            'usuario_id' => $userId,
        ]);
    }

    public function revokeAllForFirma(int $firmaId, string $reason): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at=CURRENT_TIMESTAMP(6),revoked_reason=:reason
             WHERE firma_id=:firma_id AND revoked_at IS NULL'
        );
        $statement->execute(['reason' => $reason, 'firma_id' => $firmaId]);
    }

    public function cleanupExpired(): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,CURRENT_TIMESTAMP(6)),revoked_reason=COALESCE(revoked_reason,\'expirada\')
             WHERE expires_at<=CURRENT_TIMESTAMP(6) AND revoked_at IS NULL'
        );
        $statement->execute();

        return $statement->rowCount();
    }
}
