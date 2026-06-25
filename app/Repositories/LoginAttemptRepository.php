<?php

declare(strict_types=1);

namespace App\Repositories;

final class LoginAttemptRepository extends BaseRepository
{
    public function countRecentFailures(string $emailHash, string $ip, int $minutes = 15): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email_hash=:email_hash AND ip_address=:ip AND successful=0
               AND attempted_at>=:cutoff'
        );
        $statement->bindValue(':email_hash', $emailHash);
        $statement->bindValue(':ip', $ip);
        $statement->bindValue(':cutoff', (new \DateTimeImmutable())->modify('-' . max(1, $minutes) . ' minutes')->format('Y-m-d H:i:s.u'));
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function record(?int $firmaId, string $emailHash, string $ip, bool $successful): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (firma_id,email_hash,ip_address,successful,attempted_at)
             VALUES (:firma_id,:email_hash,:ip_address,:successful,CURRENT_TIMESTAMP(6))'
        );
        $statement->bindValue(':firma_id', $firmaId, $firmaId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $statement->bindValue(':email_hash', $emailHash);
        $statement->bindValue(':ip_address', $ip);
        $statement->bindValue(':successful', $successful ? 1 : 0, \PDO::PARAM_INT);
        $statement->execute();
    }
}
