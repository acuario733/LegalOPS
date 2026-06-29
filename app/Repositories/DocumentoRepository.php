<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['d.firma_id=:firma_id', 'd.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if (($filters['cliente_id'] ?? 0) > 0) {
            $where[] = 'd.cliente_id=:cliente_id';
            $params['cliente_id'] = $filters['cliente_id'];
        }
        if (($filters['caso_id'] ?? 0) > 0) {
            $where[] = 'd.caso_id=:caso_id';
            $params['caso_id'] = $filters['caso_id'];
        }
        if (($filters['gasto_id'] ?? 0) > 0) {
            $where[] = 'd.gasto_id=:gasto_id';
            $params['gasto_id'] = $filters['gasto_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(d.titulo_normalizado LIKE :q OR d.tipo_documental LIKE :q_raw)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['q_raw'] = '%' . $filters['q_raw'] . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM documentos d' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT d.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo,
                    v.id AS version_id, v.version_numero, v.nombre_original, v.mime_detectado, v.size_bytes, v.checksum_sha256
             FROM documentos d
             LEFT JOIN clientes cl ON cl.id=d.cliente_id AND cl.firma_id=d.firma_id
             LEFT JOIN casos c ON c.id=d.caso_id AND c.firma_id=d.firma_id
             LEFT JOIN documento_versiones v ON v.id=d.current_version_id' . $sqlWhere . '
             ORDER BY d.updated_at DESC, d.id DESC
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
            'SELECT d.*, cl.nombre_razon_social AS cliente_nombre, c.titulo AS caso_titulo,
                    v.id AS version_id, v.version_numero, v.nombre_original, v.mime_detectado, v.size_bytes, v.checksum_sha256, v.storage_path
             FROM documentos d
             LEFT JOIN clientes cl ON cl.id=d.cliente_id AND cl.firma_id=d.firma_id
             LEFT JOIN casos c ON c.id=d.caso_id AND c.firma_id=d.firma_id
             LEFT JOIN documento_versiones v ON v.id=d.current_version_id
             WHERE d.id=:id AND d.firma_id=:firma_id AND d.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function searchForSelect(int $firmaId, string $query, ?int $clienteId = null, int $limit = 20): array
    {
        $where = ['d.firma_id=:firma_id', 'd.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if ($clienteId !== null) {
            $where[] = 'd.cliente_id=:cliente_id';
            $params['cliente_id'] = $clienteId;
        }
        $normalized = preg_replace('/\s+/', ' ', mb_strtolower(trim($query))) ?? '';
        $raw = mb_substr(trim($query), 0, 180);
        if ($normalized !== '' || $raw !== '') {
            $where[] = '(d.titulo_normalizado LIKE :q OR d.tipo_documental LIKE :raw)';
            $params['q'] = '%' . mb_substr($normalized, 0, 180) . '%';
            $params['raw'] = '%' . $raw . '%';
        }
        $statement = $this->pdo->prepare(
            'SELECT d.id,d.cliente_id,d.caso_id,d.titulo,d.tipo_documental,cl.nombre_razon_social AS cliente_nombre
             FROM documentos d
             LEFT JOIN clientes cl ON cl.id=d.cliente_id AND cl.firma_id=d.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY d.updated_at DESC, d.id DESC
             LIMIT :limit'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO documentos
            (firma_id,cliente_id,caso_id,gasto_id,titulo,titulo_normalizado,descripcion,tipo_documental,estado,visible_portal,created_by_usuario_id,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:caso_id,:gasto_id,:titulo,:titulo_normalizado,:descripcion,:tipo_documental,:estado,:visible_portal,:created_by_usuario_id,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $payload = [
            'cliente_id' => $data['cliente_id'],
            'caso_id' => $data['caso_id'],
            'gasto_id' => $data['gasto_id'],
            'titulo' => $data['titulo'],
            'titulo_normalizado' => $data['titulo_normalizado'],
            'descripcion' => $data['descripcion'],
            'tipo_documental' => $data['tipo_documental'],
            'estado' => $data['estado'],
            'visible_portal' => $data['visible_portal'],
        ];
        $statement = $this->pdo->prepare(
            'UPDATE documentos
             SET cliente_id=:cliente_id,caso_id=:caso_id,gasto_id=:gasto_id,titulo=:titulo,titulo_normalizado=:titulo_normalizado,
                 descripcion=:descripcion,tipo_documental=:tipo_documental,estado=:estado,visible_portal=:visible_portal,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($payload + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function setCurrentVersion(int $firmaId, int $id, int $versionId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE documentos SET current_version_id=:version_id, updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['version_id' => $versionId, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function softDelete(int $firmaId, int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE documentos SET deleted_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
    }
}
