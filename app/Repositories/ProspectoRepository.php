<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProspectoRepository extends BaseRepository
{
    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['p.firma_id = :firma_id', 'p.deleted_at IS NULL'];
        $params = ['firma_id' => $firmaId];
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(p.nombre_normalizado LIKE :q OR p.empresa_normalizada LIKE :q OR p.documento_normalizado LIKE :documento_q)';
            $params['q'] = '%' . $filters['q'] . '%';
            $params['documento_q'] = '%' . ($filters['documento_query'] ?? '') . '%';
        }

        $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM prospectos p' . $sqlWhere);
        $count->execute($params);

        $offset = max(0, ($page - 1) * $perPage);
        $query = $this->pdo->prepare(
            'SELECT p.id, p.firma_id, p.nombre, p.tipo_persona, p.email, p.telefono, p.empresa,
                    p.fuente, p.estado, p.valor_estimado, p.responsable_usuario_id,
                    p.converted_cliente_id, p.converted_at, p.created_at, p.updated_at,
                    u.nombre AS responsable_nombre, c.nombre_razon_social AS cliente_nombre
             FROM prospectos p
             LEFT JOIN usuarios u ON u.id=p.responsable_usuario_id AND u.firma_id=p.firma_id
             LEFT JOIN clientes c ON c.id=p.converted_cliente_id AND c.firma_id=p.firma_id' . $sqlWhere . '
             ORDER BY FIELD(p.estado, \'nuevo\',\'contactado\',\'consulta\',\'cotizacion\',\'negociacion\',\'ganado\',\'perdido\'), p.updated_at DESC
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
            'SELECT p.*, u.nombre AS responsable_nombre, c.nombre_razon_social AS cliente_nombre
             FROM prospectos p
             LEFT JOIN usuarios u ON u.id=p.responsable_usuario_id AND u.firma_id=p.firma_id
             LEFT JOIN clientes c ON c.id=p.converted_cliente_id AND c.firma_id=p.firma_id
             WHERE p.id=:id AND p.firma_id=:firma_id AND p.deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findForUpdate(int $firmaId, int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM prospectos WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL FOR UPDATE');
        $statement->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO prospectos
            (firma_id,nombre,nombre_normalizado,tipo_persona,email,telefono,tipo_documento,numero_documento,
             documento_normalizado,documento_hash,empresa,empresa_normalizada,fuente,estado,responsable_usuario_id,
             valor_estimado,notas,tratamiento_datos_autorizado,created_at,updated_at)
             VALUES
            (:firma_id,:nombre,:nombre_normalizado,:tipo_persona,:email,:telefono,:tipo_documento,:numero_documento,
             :documento_normalizado,:documento_hash,:empresa,:empresa_normalizada,:fuente,:estado,:responsable_usuario_id,
             :valor_estimado,:notas,:tratamiento_datos_autorizado,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE prospectos
             SET nombre=:nombre,nombre_normalizado=:nombre_normalizado,tipo_persona=:tipo_persona,
                 email=:email,telefono=:telefono,tipo_documento=:tipo_documento,numero_documento=:numero_documento,
                 documento_normalizado=:documento_normalizado,documento_hash=:documento_hash,
                 empresa=:empresa,empresa_normalizada=:empresa_normalizada,fuente=:fuente,estado=:estado,
                 responsable_usuario_id=:responsable_usuario_id,valor_estimado=:valor_estimado,notas=:notas,
                 tratamiento_datos_autorizado=:tratamiento_datos_autorizado,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute($data + ['id' => $id, 'firma_id' => $firmaId]);
    }

    public function setStatus(int $firmaId, int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE prospectos SET estado=:estado,updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['estado' => $status, 'id' => $id, 'firma_id' => $firmaId]);
    }

    public function markConverted(int $firmaId, int $id, int $clienteId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE prospectos
             SET estado=\'ganado\', converted_cliente_id=:cliente_id, converted_at=CURRENT_TIMESTAMP(6), updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['cliente_id' => $clienteId, 'id' => $id, 'firma_id' => $firmaId]);
    }
}
