<?php

declare(strict_types=1);

namespace App\Repositories;

final class UsuarioCambiosSensiblesRepository extends BaseRepository
{
    /** @param array<string, mixed> $data */
    public function insert(array $data): void
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

    /** @return list<array<string, mixed>> */
    public function historyForUser(int $firmaId, int $userId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.campo, c.valor_anterior_enmascarado, c.valor_nuevo_enmascarado,
                    c.origen, c.ip_address, c.user_agent, c.created_at,
                    c.usuario_actor_id, c.usuario_afectado_id
             FROM usuario_cambios_sensibles c
             WHERE c.firma_id = :firma_id
               AND c.usuario_afectado_id = :usuario_id
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':firma_id', $firmaId, \PDO::PARAM_INT);
        $statement->bindValue(':usuario_id', $userId, \PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(200, $limit)), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countForUser(int $firmaId, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usuario_cambios_sensibles
             WHERE firma_id = :firma_id AND usuario_afectado_id = :usuario_id'
        );
        $statement->execute(['firma_id' => $firmaId, 'usuario_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
