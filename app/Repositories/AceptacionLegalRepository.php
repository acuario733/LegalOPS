<?php

declare(strict_types=1);

namespace App\Repositories;

final class AceptacionLegalRepository extends BaseRepository
{
    public function exists(int $userId, int $documentId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM aceptaciones_legales WHERE usuario_id=:usuario_id AND documento_legal_id=:documento_id');
        $statement->execute(['usuario_id' => $userId, 'documento_id' => $documentId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(?int $firmaId, int $userId, int $documentId, string $ip, string $userAgent, string $evidenceHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO aceptaciones_legales
            (firma_id,usuario_id,documento_legal_id,ip_address,user_agent,evidence_hash,accepted_at,created_at)
            VALUES (:firma_id,:usuario_id,:documento_id,:ip,:user_agent,:evidence_hash,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->bindValue(':firma_id', $firmaId, $firmaId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $statement->bindValue(':usuario_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':documento_id', $documentId, \PDO::PARAM_INT);
        $statement->bindValue(':ip', $ip);
        $statement->bindValue(':user_agent', mb_substr($userAgent, 0, 255));
        $statement->bindValue(':evidence_hash', $evidenceHash);
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }
}
