<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;

final class RetainerService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data): int
    {
        $amount = round((float) ($data['monto'] ?? 0), 2);
        $day = max(1, min(28, (int) ($data['dia_cobro'] ?? 1)));
        if ($amount <= 0) {
            throw new HttpException(422, 'El monto del retainer debe ser mayor a cero.', ['monto' => 'Monto invalido.']);
        }
        $next = $this->nextChargeDate($day);
        $statement = $this->pdo->prepare(
            'INSERT INTO honorario_retainers
             (firma_id,cliente_id,caso_id,monto,moneda,dia_cobro,activo,proximo_cobro_at,created_at,updated_at)
             VALUES (:firma_id,:cliente_id,:caso_id,:monto,:moneda,:dia_cobro,1,:proximo_cobro_at,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'cliente_id' => (int) ($data['cliente_id'] ?? 0),
            'caso_id' => isset($data['caso_id']) ? (int) $data['caso_id'] : null,
            'monto' => number_format($amount, 2, '.', ''),
            'moneda' => strtoupper(mb_substr((string) ($data['moneda'] ?? 'COP'), 0, 3)),
            'dia_cobro' => $day,
            'proximo_cobro_at' => $next,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function pause(int $firmaId, int $id): void
    {
        $this->setActive($firmaId, $id, false);
    }

    public function reactivate(int $firmaId, int $id): void
    {
        $this->setActive($firmaId, $id, true);
    }

    /** @return list<array<string, mixed>> */
    public function active(int $firmaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM honorario_retainers
             WHERE firma_id=:firma_id AND activo=1 AND deleted_at IS NULL ORDER BY proximo_cobro_at,id'
        );
        $statement->execute(['firma_id' => $firmaId]);

        return $statement->fetchAll();
    }

    private function setActive(int $firmaId, int $id, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE honorario_retainers SET activo=:activo,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['activo' => $active ? 1 : 0, 'id' => $id, 'firma_id' => $firmaId]);
        if ($statement->rowCount() !== 1) {
            throw new HttpException(404, 'El retainer no existe en la firma.');
        }
    }

    private function nextChargeDate(int $day): string
    {
        $today = new \DateTimeImmutable('today');
        $candidate = $today->setDate((int) $today->format('Y'), (int) $today->format('m'), $day);
        if ($candidate < $today) {
            $candidate = $candidate->modify('first day of next month')->setDate(
                (int) $candidate->modify('first day of next month')->format('Y'),
                (int) $candidate->modify('first day of next month')->format('m'),
                $day
            );
        }

        return $candidate->format('Y-m-d');
    }
}
