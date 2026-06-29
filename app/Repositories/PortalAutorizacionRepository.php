<?php

declare(strict_types=1);

namespace App\Repositories;

final class PortalAutorizacionRepository extends BaseRepository
{
    /** @return list<array<string, mixed>> */
    public function overview(int $firmaId, int $clienteId): array
    {
        $items = [];
        foreach ([
            'SELECT cpp.id, \'caso\' tipo, cpp.caso_id recurso_id, c.titulo nombre, CONCAT(\'/casos/\', cpp.caso_id) url, cpp.estado, cpp.observacion_publica
             FROM caso_permisos_portal cpp
             INNER JOIN casos c ON c.id=cpp.caso_id AND c.firma_id=cpp.firma_id
             WHERE cpp.firma_id=:firma_id AND cpp.cliente_id=:cliente_id',
            'SELECT dpp.id, \'documento\' tipo, dpp.documento_id recurso_id, d.titulo nombre, CONCAT(\'/documentos/\', dpp.documento_id) url, dpp.estado, dpp.observacion_publica
             FROM documento_permisos_portal dpp
             INNER JOIN documentos d ON d.id=dpp.documento_id AND d.firma_id=dpp.firma_id
             WHERE dpp.firma_id=:firma_id AND dpp.cliente_id=:cliente_id',
            'SELECT fpp.id, fpp.tipo_finanza tipo, COALESCE(fpp.honorario_id,fpp.pago_id,fpp.gasto_id) recurso_id,
                    CASE
                        WHEN fpp.tipo_finanza=\'honorario\' THEN COALESCE(h.concepto, \'Honorario\')
                        WHEN fpp.tipo_finanza=\'pago\' THEN CONCAT(\'Pago \', COALESCE(p.fecha_pago, \'\'))
                        WHEN fpp.tipo_finanza=\'gasto\' THEN COALESCE(g.concepto, \'Gasto\')
                        ELSE fpp.tipo_finanza
                    END nombre,
                    CASE
                        WHEN fpp.tipo_finanza=\'honorario\' THEN CONCAT(\'/finanzas/honorarios?cliente_id=\', fpp.cliente_id)
                        WHEN fpp.tipo_finanza=\'pago\' THEN CONCAT(\'/finanzas/pagos?cliente_id=\', fpp.cliente_id)
                        WHEN fpp.tipo_finanza=\'gasto\' THEN CONCAT(\'/finanzas/gastos?cliente_id=\', fpp.cliente_id)
                        ELSE NULL
                    END url,
                    fpp.estado, fpp.observacion_publica
             FROM finanza_permisos_portal fpp
             LEFT JOIN honorarios h ON h.id=fpp.honorario_id AND h.firma_id=fpp.firma_id
             LEFT JOIN pagos p ON p.id=fpp.pago_id AND p.firma_id=fpp.firma_id
             LEFT JOIN gastos g ON g.id=fpp.gasto_id AND g.firma_id=fpp.firma_id
             WHERE fpp.firma_id=:firma_id AND fpp.cliente_id=:cliente_id',
            'SELECT puc.usuario_id id, \'usuario_cliente\' tipo, puc.usuario_id recurso_id, CONCAT(u.nombre, \' - \', u.email) nombre, CONCAT(\'/usuarios?tipo=cliente_externo&usuario_id=\', puc.usuario_id) url,
                    CASE WHEN puc.estado=\'activo\' THEN \'autorizado\' ELSE \'revocado\' END estado, NULL observacion_publica
             FROM portal_usuario_clientes puc
             INNER JOIN usuarios u ON u.id=puc.usuario_id AND u.firma_id=puc.firma_id
             WHERE puc.firma_id=:firma_id AND puc.cliente_id=:cliente_id',
        ] as $sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);
            $items = array_merge($items, $statement->fetchAll());
        }

        return $items;
    }

    /** @param array<string, mixed> $data */
    public function upsertCase(array $data): void
    {
        $this->executeUpsert('caso_permisos_portal', 'caso_id', $data);
    }

    /** @param array<string, mixed> $data */
    public function upsertDocument(array $data): void
    {
        $this->executeUpsert('documento_permisos_portal', 'documento_id', $data);
    }

    /** @param array<string, mixed> $data */
    public function upsertFinance(array $data, string $column): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO finanza_permisos_portal
            (firma_id,cliente_id,tipo_finanza,' . $column . ',estado,observacion_publica,autorizado_por_usuario_id,autorizado_at,revocado_at,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:tipo_finanza,:recurso_id,:estado,:observacion_publica,:usuario_id,CURRENT_TIMESTAMP(6),:revocado_at,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE estado=VALUES(estado),observacion_publica=VALUES(observacion_publica),autorizado_por_usuario_id=VALUES(autorizado_por_usuario_id),autorizado_at=CURRENT_TIMESTAMP(6),revocado_at=VALUES(revocado_at),updated_at=CURRENT_TIMESTAMP(6)'
        );
        $statement->execute($data);
    }

    public function linkUserClient(int $firmaId, int $usuarioId, int $clienteId, int $creatorId, string $estado): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO portal_usuario_clientes (firma_id,usuario_id,cliente_id,estado,created_by_usuario_id,created_at,updated_at)
             VALUES (:firma_id,:usuario_id,:cliente_id,:estado,:creator_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE estado=VALUES(estado),updated_at=CURRENT_TIMESTAMP(6)'
        );
        $statement->execute(['firma_id' => $firmaId, 'usuario_id' => $usuarioId, 'cliente_id' => $clienteId, 'estado' => $estado === 'autorizado' ? 'activo' : 'inactivo', 'creator_id' => $creatorId]);
    }

    public function insertObservation(
        int $firmaId,
        int $clienteId,
        string $resourceType,
        int $resourceId,
        ?string $publicNote,
        ?string $internalNote,
        int $creatorId
    ): void {
        $column = match ($resourceType) {
            'caso' => 'caso_id',
            'documento' => 'documento_id',
            'honorario' => 'honorario_id',
            'pago' => 'pago_id',
            'gasto' => 'gasto_id',
            default => null,
        };
        if ($column === null) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO portal_observaciones
            (firma_id,cliente_id,' . $column . ',observacion_publica,observacion_interna,created_by_usuario_id,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:recurso_id,:observacion_publica,:observacion_interna,:creator_id,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'cliente_id' => $clienteId,
            'recurso_id' => $resourceId,
            'observacion_publica' => $publicNote,
            'observacion_interna' => $internalNote,
            'creator_id' => $creatorId,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function executeUpsert(string $table, string $column, array $data): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $table . '
            (firma_id,cliente_id,' . $column . ',estado,observacion_publica,autorizado_por_usuario_id,autorizado_at,revocado_at,created_at,updated_at)
             VALUES
            (:firma_id,:cliente_id,:recurso_id,:estado,:observacion_publica,:usuario_id,CURRENT_TIMESTAMP(6),:revocado_at,CURRENT_TIMESTAMP(6),CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE estado=VALUES(estado),observacion_publica=VALUES(observacion_publica),autorizado_por_usuario_id=VALUES(autorizado_por_usuario_id),autorizado_at=CURRENT_TIMESTAMP(6),revocado_at=VALUES(revocado_at),updated_at=CURRENT_TIMESTAMP(6)'
        );
        $statement->execute($data);
    }
}
