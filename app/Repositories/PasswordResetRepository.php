<?php

declare(strict_types=1);

namespace App\Repositories;

final class PasswordResetRepository extends BaseRepository
{
    public function create(int $userId, ?int $firmaId, string $tokenHash, string $ip, string $userAgent, int $minutes = 30): void
    {
        $invalidate = $this->pdo->prepare('UPDATE password_resets SET used_at=CURRENT_TIMESTAMP(6) WHERE usuario_id=:usuario_id AND used_at IS NULL');
        $invalidate->execute(['usuario_id' => $userId]);
        $statement = $this->pdo->prepare(
            'INSERT INTO password_resets
            (firma_id,usuario_id,token_hash,requested_ip,requested_user_agent,expires_at,created_at)
            VALUES (:firma_id,:usuario_id,:token_hash,:ip,:user_agent,:expires_at,CURRENT_TIMESTAMP(6))'
        );
        $statement->bindValue(':firma_id', $firmaId, $firmaId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $statement->bindValue(':usuario_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':token_hash', $tokenHash);
        $statement->bindValue(':ip', $ip);
        $statement->bindValue(':user_agent', mb_substr($userAgent, 0, 255));
        $statement->bindValue(':expires_at', (new \DateTimeImmutable())->modify('+' . max(1, $minutes) . ' minutes')->format('Y-m-d H:i:s.u'));
        $statement->execute();
    }

    /** @return array<string, mixed>|null */
    public function findValid(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT pr.*,u.email,u.estado FROM password_resets pr
             INNER JOIN usuarios u ON u.id=pr.usuario_id
             WHERE pr.token_hash=:token_hash AND pr.used_at IS NULL AND pr.expires_at>CURRENT_TIMESTAMP(6) AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function markUsed(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE password_resets SET used_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND used_at IS NULL');
        $statement->execute(['id' => $id]);
    }
}
