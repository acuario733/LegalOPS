<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class HonorarioLineaService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param list<array<string, mixed>> $lineas */
    public function reemplazarLineas(int $firmaId, int $honorarioId, array $lineas): void
    {
        // TENANT FILTER: firma_id = ?
        $delete = $this->pdo->prepare('UPDATE honorario_lineas SET deleted_at=CURRENT_TIMESTAMP(6) WHERE firma_id=:firma_id AND honorario_id=:honorario_id AND deleted_at IS NULL');
        $delete->execute(['firma_id' => $firmaId, 'honorario_id' => $honorarioId]);

        foreach ($lineas as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 1);
            $tarifa = (float) ($linea['tarifa'] ?? $linea['monto'] ?? 0);
            $monto = round($cantidad * $tarifa, 2);
            // TENANT FILTER: firma_id = ?
            $insert = $this->pdo->prepare(
                'INSERT INTO honorario_lineas
                 (firma_id,honorario_id,descripcion,cantidad,tarifa,monto,tipo,referencia_id,created_at)
                 VALUES (:firma_id,:honorario_id,:descripcion,:cantidad,:tarifa,:monto,:tipo,:referencia_id,CURRENT_TIMESTAMP(6))'
            );
            $insert->execute([
                'firma_id' => $firmaId,
                'honorario_id' => $honorarioId,
                'descripcion' => mb_substr(trim((string) ($linea['descripcion'] ?? 'Honorario')), 0, 500),
                'cantidad' => number_format(max(0.01, $cantidad), 2, '.', ''),
                'tarifa' => number_format(max(0, $tarifa), 2, '.', ''),
                'monto' => number_format(max(0, $monto), 2, '.', ''),
                'tipo' => in_array(($linea['tipo'] ?? ''), ['time_entry', 'gasto', 'honorario_manual'], true) ? $linea['tipo'] : 'honorario_manual',
                'referencia_id' => filter_var($linea['referencia_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
            ]);
        }
        $this->syncTotal($firmaId, $honorarioId);
    }

    /** @return list<array<string, mixed>> */
    public function listar(int $firmaId, int $honorarioId): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT id,descripcion,cantidad,tarifa,monto,tipo,referencia_id,created_at
             FROM honorario_lineas
             WHERE firma_id=:firma_id AND honorario_id=:honorario_id AND deleted_at IS NULL
             ORDER BY id ASC'
        );
        $statement->execute(['firma_id' => $firmaId, 'honorario_id' => $honorarioId]);

        return $statement->fetchAll();
    }

    /** @return array{subtotal: string, impuesto: string, total: string} */
    public function calcularTotales(int $firmaId, int $honorarioId): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(monto),0) FROM honorario_lineas WHERE firma_id=:firma_id AND honorario_id=:honorario_id AND deleted_at IS NULL');
        $statement->execute(['firma_id' => $firmaId, 'honorario_id' => $honorarioId]);
        $subtotal = (float) $statement->fetchColumn();

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'impuesto' => '0.00',
            'total' => number_format($subtotal, 2, '.', ''),
        ];
    }

    private function syncTotal(int $firmaId, int $honorarioId): void
    {
        $totals = $this->calcularTotales($firmaId, $honorarioId);
        // TENANT FILTER: firma_id = ?
        $update = $this->pdo->prepare('UPDATE honorarios SET monto=:monto, updated_at=CURRENT_TIMESTAMP(6) WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $update->execute(['monto' => $totals['total'], 'id' => $honorarioId, 'firma_id' => $firmaId]);
    }
}
