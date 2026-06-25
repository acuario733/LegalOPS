<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ClienteRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['firma_id = :firma_id', 'deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];

        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['tipo_persona'] ?? '') !== '') {
            $where[] = 'tipo_persona = :tipo_persona';
            $params['tipo_persona'] = $filters['tipo_persona'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(nombre_normalizado LIKE :q OR documento_normalizado LIKE :documento_q OR documento_hash = :documento_hash)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['documento_q'] = '%' . ($filters['documento_query'] ?? '') . '%';
            $params['documento_hash'] = $filters['documento_hash'] ?? '';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM clientes' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT id, firma_id, tipo_persona, nombre_razon_social, tipo_documento, numero_documento,
                    email, telefono, direccion, estado, origen, tratamiento_datos_autorizado,
                    autorizacion_tratamiento_at, created_at, updated_at
             FROM clientes' . $sqlWhere . '
             ORDER BY nombre_normalizado ASC, id ASC
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
        $statement = $this->pdo->prepare('SELECT * FROM clientes WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function documentHashExists(int $firmaId, string $documentHash, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM clientes WHERE firma_id=:firma_id AND documento_hash=:documento_hash AND deleted_at IS NULL';
        $params = ['firma_id' => $firmaId, 'documento_hash' => $documentHash];
        if ($excludeId !== null) {
            $sql .= ' AND id<>:id';
            $params['id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array<string, mixed>|null */
    public function findByDocumentHash(int $firmaId, string $documentHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM clientes
             WHERE firma_id=:firma_id AND documento_hash=:documento_hash AND deleted_at IS NULL
             ORDER BY id ASC LIMIT 1'
        );
        $statement->execute(['firma_id' => $firmaId, 'documento_hash' => $documentHash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO clientes
            (firma_id, tipo_persona, nombre_razon_social, nombre_normalizado, tipo_documento,
             numero_documento, documento_normalizado, documento_hash, email, telefono, direccion,
             estado, origen, observaciones, tratamiento_datos_autorizado, autorizacion_tratamiento_at,
             created_at, updated_at)
            VALUES
            (:firma_id, :tipo_persona, :nombre_razon_social, :nombre_normalizado, :tipo_documento,
             :numero_documento, :documento_normalizado, :documento_hash, :email, :telefono, :direccion,
             :estado, :origen, :observaciones, :tratamiento_datos_autorizado, :autorizacion_tratamiento_at,
             CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE clientes
             SET tipo_persona=:tipo_persona, nombre_razon_social=:nombre_razon_social,
                 nombre_normalizado=:nombre_normalizado, tipo_documento=:tipo_documento,
                 numero_documento=:numero_documento, documento_normalizado=:documento_normalizado,
                 documento_hash=:documento_hash, email=:email, telefono=:telefono, direccion=:direccion,
                 estado=:estado, origen=:origen, observaciones=:observaciones,
                 tratamiento_datos_autorizado=:tratamiento_datos_autorizado,
                 autorizacion_tratamiento_at=:autorizacion_tratamiento_at,
                 updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function softDelete(int $firmaId, int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE clientes SET deleted_at=CURRENT_TIMESTAMP(6), updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
    }

    /** @param array<string, mixed> $data */
    public function createAuthorization(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO cliente_autorizaciones
            (firma_id, cliente_id, tipo, estado, medio, version_texto, evidencia_hash,
             observacion, registrado_por_usuario_id, created_at)
            VALUES
            (:firma_id, :cliente_id, :tipo, :estado, :medio, :version_texto, :evidencia_hash,
             :observacion, :registrado_por_usuario_id, CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function authorizations(int $firmaId, int $clienteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ca.id, ca.tipo, ca.estado, ca.medio, ca.version_texto, ca.observacion,
                    ca.registrado_por_usuario_id, ca.revoked_at, ca.created_at, u.nombre AS registrado_por
             FROM cliente_autorizaciones ca
             LEFT JOIN usuarios u ON u.id=ca.registrado_por_usuario_id
             WHERE ca.firma_id=:firma_id AND ca.cliente_id=:cliente_id
             ORDER BY ca.created_at DESC, ca.id DESC'
        );
        $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $statement->fetchAll();
    }
}
