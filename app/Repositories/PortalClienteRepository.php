<?php

declare(strict_types=1);

namespace App\Repositories;

final class PortalClienteRepository extends BaseRepository
{
    /** @return array<string, mixed>|null */
    public function clientForUser(int $firmaId, int $usuarioId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT cl.*
             FROM portal_usuario_clientes puc
             INNER JOIN clientes cl ON cl.id=puc.cliente_id AND cl.firma_id=puc.firma_id
             WHERE puc.firma_id=:firma_id AND puc.usuario_id=:usuario_id AND puc.estado=\'activo\' AND cl.deleted_at IS NULL
             ORDER BY puc.id LIMIT 1'
        );
        $statement->execute(['firma_id' => $firmaId, 'usuario_id' => $usuarioId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function cases(int $firmaId, int $clienteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id,c.titulo,c.estado,c.prioridad,c.radicado,cpp.observacion_publica
             FROM caso_permisos_portal cpp
             INNER JOIN casos c ON c.id=cpp.caso_id AND c.firma_id=cpp.firma_id AND c.cliente_id=cpp.cliente_id
             WHERE cpp.firma_id=:firma_id AND cpp.cliente_id=:cliente_id AND cpp.estado=\'autorizado\' AND c.deleted_at IS NULL
             ORDER BY c.updated_at DESC'
        );
        $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function documents(int $firmaId, int $clienteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id,d.titulo,d.tipo_documental,d.updated_at,v.nombre_original,v.size_bytes,dpp.observacion_publica
             FROM documento_permisos_portal dpp
             INNER JOIN documentos d ON d.id=dpp.documento_id AND d.firma_id=dpp.firma_id AND d.cliente_id=dpp.cliente_id
             LEFT JOIN documento_versiones v ON v.id=d.current_version_id
             WHERE dpp.firma_id=:firma_id AND dpp.cliente_id=:cliente_id AND dpp.estado=\'autorizado\' AND d.deleted_at IS NULL
             ORDER BY d.updated_at DESC'
        );
        $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function authorizedDocument(int $firmaId, int $clienteId, int $documentId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*
             FROM documento_permisos_portal dpp
             INNER JOIN documentos d ON d.id=dpp.documento_id AND d.firma_id=dpp.firma_id AND d.cliente_id=dpp.cliente_id
             WHERE dpp.firma_id=:firma_id AND dpp.cliente_id=:cliente_id AND dpp.documento_id=:documento_id
               AND dpp.estado=\'autorizado\' AND d.deleted_at IS NULL'
        );
        $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId, 'documento_id' => $documentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function finances(int $firmaId, int $clienteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT fpp.tipo_finanza, fpp.estado, fpp.observacion_publica,
                    h.concepto AS honorario_concepto, h.monto AS honorario_monto,
                    p.fecha_pago, p.monto AS pago_monto, p.metodo_pago,
                    g.concepto AS gasto_concepto, g.monto AS gasto_monto, g.fecha_gasto
             FROM finanza_permisos_portal fpp
             LEFT JOIN honorarios h ON h.id=fpp.honorario_id AND h.firma_id=fpp.firma_id AND h.cliente_id=fpp.cliente_id
             LEFT JOIN pagos p ON p.id=fpp.pago_id AND p.firma_id=fpp.firma_id AND p.cliente_id=fpp.cliente_id
             LEFT JOIN gastos g ON g.id=fpp.gasto_id AND g.firma_id=fpp.firma_id AND g.cliente_id=fpp.cliente_id
             WHERE fpp.firma_id=:firma_id AND fpp.cliente_id=:cliente_id AND fpp.estado=\'autorizado\'
               AND (
                    (fpp.tipo_finanza=\'honorario\' AND h.deleted_at IS NULL)
                 OR (fpp.tipo_finanza=\'pago\' AND p.deleted_at IS NULL)
                 OR (fpp.tipo_finanza=\'gasto\' AND g.deleted_at IS NULL)
               )
             ORDER BY fpp.updated_at DESC'
        );
        $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $statement->fetchAll();
    }

    public function logAccess(int $firmaId, int $usuarioId, int $clienteId, string $action, ?string $entityType, ?int $entityId, ?string $ip, ?string $userAgent): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO portal_accesos (firma_id,usuario_id,cliente_id,accion,entidad_tipo,entidad_id,ip_address,user_agent,created_at)
             VALUES (:firma_id,:usuario_id,:cliente_id,:accion,:entidad_tipo,:entidad_id,:ip_address,:user_agent,CURRENT_TIMESTAMP(6))'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'usuario_id' => $usuarioId,
            'cliente_id' => $clienteId,
            'accion' => $action,
            'entidad_tipo' => $entityType,
            'entidad_id' => $entityId,
            'ip_address' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);
    }
}
