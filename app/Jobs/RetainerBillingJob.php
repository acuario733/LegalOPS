<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BillingService;
use PDO;
use RuntimeException;

final class RetainerBillingJob extends Job
{
    public function __construct(private readonly PDO $pdo, private readonly BillingService $billing)
    {
    }

    public function handle(array $payload): void
    {
        $sql = 'SELECT r.*,cl.email FROM honorario_retainers r
                INNER JOIN clientes cl ON cl.id=r.cliente_id AND cl.firma_id=r.firma_id
                WHERE r.activo=1 AND r.proximo_cobro_at<=CURRENT_DATE AND r.deleted_at IS NULL';
        $params = [];
        if (($payload['firma_id'] ?? null) !== null) {
            $sql .= ' AND r.firma_id=:firma_id';
            $params['firma_id'] = (int) $payload['firma_id'];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        foreach ($statement->fetchAll() as $retainer) {
            if (filter_var($retainer['email'], FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('El cliente del retainer ' . $retainer['id'] . ' no tiene email valido.');
            }
            $this->pdo->beginTransaction();
            try {
                $insert = $this->pdo->prepare(
                    'INSERT INTO honorarios
                     (firma_id,cliente_id,caso_id,numero,concepto,concepto_normalizado,descripcion,monto,moneda,
                      fecha_acuerdo,fecha_vencimiento,estado,retainer_id,origen,created_at,updated_at)
                     VALUES (:firma_id,:cliente_id,:caso_id,NULL,\'Retainer mensual\',\'retainer mensual\',
                             \'Cobro automatico de retainer\',:monto,:moneda,CURRENT_DATE,DATE_ADD(CURRENT_DATE,INTERVAL 15 DAY),
                             \'pendiente\',:retainer_id,\'retainer\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
                );
                $insert->execute([
                    'firma_id' => (int) $retainer['firma_id'],
                    'cliente_id' => (int) $retainer['cliente_id'],
                    'caso_id' => $retainer['caso_id'],
                    'monto' => $retainer['monto'],
                    'moneda' => $retainer['moneda'],
                    'retainer_id' => (int) $retainer['id'],
                ]);
                $honorarioId = (int) $this->pdo->lastInsertId();
                $number = 'FAC-' . str_pad((string) $honorarioId, 8, '0', STR_PAD_LEFT);
                $updateInvoice = $this->pdo->prepare('UPDATE honorarios SET numero=:numero WHERE id=:id AND firma_id=:firma_id');
                $updateInvoice->execute(['numero' => $number, 'id' => $honorarioId, 'firma_id' => (int) $retainer['firma_id']]);
                $line = $this->pdo->prepare(
                    'INSERT INTO honorario_lineas
                     (firma_id,honorario_id,descripcion,cantidad,tarifa,monto,tipo,created_at)
                     VALUES (:firma_id,:honorario_id,\'Retainer mensual\',1,:monto,:monto,\'honorario_manual\',CURRENT_TIMESTAMP)'
                );
                $line->execute(['firma_id' => (int) $retainer['firma_id'], 'honorario_id' => $honorarioId, 'monto' => $retainer['monto']]);
                $next = (new \DateTimeImmutable((string) $retainer['proximo_cobro_at']))->modify('first day of next month')
                    ->modify('+' . ((int) $retainer['dia_cobro'] - 1) . ' days')->format('Y-m-d');
                $updateRetainer = $this->pdo->prepare(
                    'UPDATE honorario_retainers SET ultimo_cobro_at=CURRENT_DATE,proximo_cobro_at=:next,updated_at=CURRENT_TIMESTAMP
                     WHERE id=:id AND firma_id=:firma_id'
                );
                $updateRetainer->execute(['next' => $next, 'id' => (int) $retainer['id'], 'firma_id' => (int) $retainer['firma_id']]);
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                $this->pdo->rollBack();
                throw $exception;
            }
            $this->billing->send((int) $retainer['firma_id'], $honorarioId, (string) $retainer['email']);
        }
    }
}
