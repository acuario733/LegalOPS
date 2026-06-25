<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AudienciaRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['a.firma_id=:firma_id', 'a.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'a.estado=:estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['caso_id'] ?? 0) > 0) {
            $where[] = 'a.caso_id=:caso_id';
            $params['caso_id'] = $filters['caso_id'];
        }
        if (($filters['desde'] ?? '') !== '') {
            $where[] = 'a.fecha>=:desde';
            $params['desde'] = $filters['desde'];
        }
        if (($filters['hasta'] ?? '') !== '') {
            $where[] = 'a.fecha<=:hasta';
            $params['hasta'] = $filters['hasta'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.titulo LIKE :q OR a.despacho LIKE :q OR a.lugar LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM audiencias a' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT a.*, c.titulo AS caso_titulo, u.nombre AS responsable_nombre
             FROM audiencias a
             INNER JOIN casos c ON c.id=a.caso_id AND c.firma_id=a.firma_id
             LEFT JOIN usuarios u ON u.id=a.responsable_usuario_id AND u.firma_id=a.firma_id' . $sqlWhere . '
             ORDER BY a.fecha ASC, a.hora ASC, a.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** @return array<string, mixed>|null */
    public function findForFirma(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.*, c.titulo AS caso_titulo, u.nombre AS responsable_nombre
             FROM audiencias a
             INNER JOIN casos c ON c.id=a.caso_id AND c.firma_id=a.firma_id
             LEFT JOIN usuarios u ON u.id=a.responsable_usuario_id AND u.firma_id=a.firma_id
             WHERE a.id=:id AND a.firma_id=:firma_id AND a.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audiencias
            (firma_id,caso_id,responsable_usuario_id,titulo,fecha,hora,timezone,modalidad,despacho,lugar,enlace,estado,created_at,updated_at)
             VALUES
            (:firma_id,:caso_id,:responsable_usuario_id,:titulo,:fecha,:hora,:timezone,:modalidad,:despacho,:lugar,:enlace,:estado,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE audiencias
             SET caso_id=:caso_id,responsable_usuario_id=:responsable_usuario_id,titulo=:titulo,fecha=:fecha,hora=:hora,
                 timezone=:timezone,modalidad=:modalidad,despacho=:despacho,lugar=:lugar,enlace=:enlace,estado=:estado,
                 updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function registerResult(int $firmaId, int $id, string $result, int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE audiencias
             SET estado=\'realizada\', resultado=:resultado, resultado_registrado_at=CURRENT_TIMESTAMP(6),
                 resultado_registrado_por_usuario_id=:usuario_id, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['resultado' => $result, 'usuario_id' => $userId, 'id' => $id, 'firma_id' => $firmaId]);
    }
}
