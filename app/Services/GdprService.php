<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Request;
use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;

/**
 * Implementa los derechos GDPR del titular de datos:
 *   - Derecho al olvido (Art. 17 GDPR): anonimiza PII conservando registros financieros.
 *   - Portabilidad de datos (Art. 20 GDPR): exporta datos del cliente como JSON en S3.
 *
 * Ambas operaciones exigen permiso `clientes.eliminar` (verificado en el controlador)
 * y quedan registradas en la auditoría.
 */
final class GdprService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AuditoriaService $audit,
        private readonly StorageService $storage,
    ) {
    }

    /**
     * Anonimiza los datos personales del cliente (Art. 17 GDPR).
     * Los registros financieros (honorarios, pagos) se conservan con el cliente_id intacto.
     *
     * @throws HttpException 404 si el cliente no existe en la firma.
     * @throws HttpException 409 si el cliente ya fue anonimizado.
     */
    public function olvidar(int $firmaId, int $clienteId, Request $request): void
    {
        $cliente = $this->findOrFail($firmaId, $clienteId);

        if ($cliente['olvidado_at'] !== null) {
            throw new HttpException(409, 'Este cliente ya fue anonimizado previamente.');
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->pdo->prepare(
            'UPDATE clientes
             SET nombre_razon_social   = :nombre,
                 nombre_normalizado    = :nombre_norm,
                 email                 = NULL,
                 telefono              = NULL,
                 numero_documento      = NULL,
                 documento_normalizado = NULL,
                 documento_hash        = NULL,
                 direccion             = NULL,
                 observaciones         = NULL,
                 olvidado_at           = :olvidado_at
             WHERE id = :id AND firma_id = :firma_id'
        )->execute([
            'nombre'      => 'Datos anonimizados',
            'nombre_norm' => 'datos anonimizados',
            'olvidado_at' => $now,
            'id'          => $clienteId,
            'firma_id'    => $firmaId,
        ]);

        $this->audit->record(
            action:     'gdpr.olvidar',
            module:     'clientes',
            entityType: 'cliente',
            entityId:   $clienteId,
            metadata:   [],
            request:    $request,
            firmaId:    $firmaId,
            severity:   'warning'
        );
    }

    /**
     * Genera un JSON con todos los datos del cliente y lo sube a S3 (Art. 20 GDPR).
     * Retorna una URL presignada válida por 24 horas, o null cuando S3 no está configurado.
     *
     * @return array{url: string|null, expires_in: int}
     * @throws HttpException 404 si el cliente no existe en la firma.
     * @throws JsonException
     */
    public function exportarDatos(int $firmaId, int $clienteId, Request $request): array
    {
        $cliente = $this->findOrFail($firmaId, $clienteId);

        $payload = [
            'version'      => '1.0',
            'exportado_at' => (new DateTimeImmutable())->format('c'),
            'cliente'      => $this->redactInternalFields($cliente),
            'casos'        => $this->casosDelCliente($firmaId, $clienteId),
            'honorarios'   => $this->honorariosDelCliente($firmaId, $clienteId),
            'pagos'        => $this->pagosDelCliente($firmaId, $clienteId),
        ];

        $json   = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $s3Key  = null;
        $url    = null;
        $ttl    = 86400;

        try {
            $path  = 'gdpr/exports/firma-' . $firmaId . '/cliente-' . $clienteId . '-' . time() . '.json';
            $s3Key = $this->storage->upload($firmaId, $path, $json, 'application/json');
            $url   = $this->storage->presignedUrl($s3Key, $ttl);
        } catch (RuntimeException) {
            // S3 no configurado — el registro se crea igual, sin URL.
        }

        $now       = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $expiresAt = (new DateTimeImmutable('+' . $ttl . ' seconds'))->format('Y-m-d H:i:s');

        $this->pdo->prepare(
            'INSERT INTO exportaciones_datos (firma_id, cliente_id, s3_key, expires_at, created_at)
             VALUES (:firma_id, :cliente_id, :s3_key, :expires_at, :created_at)'
        )->execute([
            'firma_id'   => $firmaId,
            'cliente_id' => $clienteId,
            's3_key'     => $s3Key,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $this->audit->record(
            action:     'gdpr.exportar',
            module:     'clientes',
            entityType: 'cliente',
            entityId:   $clienteId,
            metadata:   ['s3_key' => $s3Key],
            request:    $request,
            firmaId:    $firmaId,
        );

        return ['url' => $url, 'expires_in' => $ttl];
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $firmaId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM clientes WHERE id = :id AND firma_id = :firma_id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $clienteId, 'firma_id' => $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new HttpException(404, 'Cliente no encontrado.');
        }

        return $row;
    }

    /**
     * Elimina campos internos de infraestructura que no son datos del titular.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function redactInternalFields(array $row): array
    {
        unset($row['documento_hash'], $row['nombre_normalizado'], $row['documento_normalizado']);

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function casosDelCliente(int $firmaId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.numero, c.titulo, c.estado, c.tipo_proceso, c.created_at
             FROM casos c
             INNER JOIN caso_partes cp ON cp.caso_id = c.id
             WHERE c.firma_id = :firma_id AND cp.cliente_id = :cliente_id AND c.deleted_at IS NULL'
        );
        $stmt->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function honorariosDelCliente(int $firmaId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, numero_factura, estado, monto, moneda, created_at, vencimiento_at
             FROM honorarios
             WHERE firma_id = :firma_id AND cliente_id = :cliente_id AND deleted_at IS NULL'
        );
        $stmt->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function pagosDelCliente(int $firmaId, int $clienteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.monto, p.metodo, p.estado, p.created_at
             FROM pagos p
             INNER JOIN honorarios h ON h.id = p.honorario_id
             WHERE h.firma_id = :firma_id AND h.cliente_id = :cliente_id AND p.deleted_at IS NULL'
        );
        $stmt->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
