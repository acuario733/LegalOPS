<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use PDO;
use Throwable;

final class TrustService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly NotificacionService $notificaciones,
        private readonly PdfService $pdfs,
        private readonly StorageService $storage
    ) {
    }

    public function depositar(int $firmaId, int $clienteId, ?int $casoId, mixed $monto, string $descripcion, ?string $referencia = null, ?Request $request = null): array
    {
        $amount = $this->money($monto);
        $description = $this->description($descripcion);
        $this->validateClienteCaso($firmaId, $clienteId, $casoId);

        return $this->transaction(function () use ($firmaId, $clienteId, $casoId, $amount, $description, $referencia, $request): array {
            $account = $this->accountForUpdate($firmaId, $clienteId, $casoId);
            // TENANT FILTER: firma_id = ?
            $update = $this->pdo->prepare(
                'UPDATE trust_accounts
                 SET saldo=saldo + :monto, updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
            );
            $update->execute(['monto' => $amount, 'id' => (int) $account['id'], 'firma_id' => $firmaId]);
            $transaction = $this->insertTransaction($firmaId, (int) $account['id'], 'deposito', $amount, $description, $referencia);
            $this->audit->record('TRUST_DEPOSITO_REGISTRADO', 'trust', 'trust_transaction', (int) $transaction['id'], [
                'trust_account_id' => (int) $account['id'],
                'cliente_id' => $clienteId,
                'caso_id' => $casoId,
                'monto' => $amount,
            ], $request, $firmaId, 'warning');

            return $transaction;
        });
    }

    public function retirar(int $firmaId, int $trustAccountId, mixed $monto, string $descripcion, ?string $referencia = null, ?int $clienteId = null, ?Request $request = null): array
    {
        $amount = $this->money($monto);
        $description = $this->description($descripcion);

        return $this->transaction(function () use ($firmaId, $trustAccountId, $amount, $description, $referencia, $clienteId, $request): array {
            $account = $this->findAccountForUpdate($firmaId, $trustAccountId);
            if ($clienteId !== null) {
                $this->validarSeparacionFondos($firmaId, $trustAccountId, $clienteId);
            }
            if ((float) $account['saldo'] + 0.00001 < (float) $amount) {
                throw new HttpException(422, 'Saldo insuficiente en cuenta de deposito', ['monto' => ['Saldo insuficiente en cuenta de deposito']]);
            }
            // TENANT FILTER: firma_id = ?
            $update = $this->pdo->prepare(
                'UPDATE trust_accounts
                 SET saldo=saldo - :monto, updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
            );
            $update->execute(['monto' => $amount, 'id' => $trustAccountId, 'firma_id' => $firmaId]);
            $transaction = $this->insertTransaction($firmaId, $trustAccountId, 'retiro', $amount, $description, $referencia);
            $this->audit->record('TRUST_RETIRO_REGISTRADO', 'trust', 'trust_transaction', (int) $transaction['id'], [
                'trust_account_id' => $trustAccountId,
                'cliente_id' => (int) $account['cliente_id'],
                'monto' => $amount,
            ], $request, $firmaId, 'warning');
            $this->notificaciones->alertarSaldoBajoTrust($firmaId, $trustAccountId, 0.0);

            return $transaction;
        });
    }

    public function getSaldo(int $firmaId, int $clienteId): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT a.id,a.firma_id,a.cliente_id,a.caso_id,a.moneda,a.saldo,a.updated_at,
                    (SELECT MAX(t.created_at) FROM trust_transactions t WHERE t.firma_id=a.firma_id AND t.trust_account_id=a.id) AS ultimo_movimiento_at
             FROM trust_accounts a
             WHERE a.firma_id=:firma_id AND a.cliente_id=:cliente_id AND a.deleted_at IS NULL
             ORDER BY a.caso_id IS NULL DESC, a.id ASC'
        );
        $statement->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId]);
        $accounts = $statement->fetchAll();
        $total = array_reduce($accounts, static fn (float $sum, array $row): float => $sum + (float) $row['saldo'], 0.0);

        return ['saldo_total' => number_format($total, 2, '.', ''), 'cuentas' => $accounts];
    }

    public function getLibro(int $firmaId, ?int $clienteId, ?string $fechaDesde, ?string $fechaHasta): array
    {
        $where = ['t.firma_id=:firma_id'];
        $params = ['firma_id' => $firmaId];
        if ($clienteId !== null) {
            $where[] = 'a.cliente_id=:cliente_id';
            $params['cliente_id'] = $clienteId;
        }
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $where[] = 't.fecha>=:desde';
            $params['desde'] = $fechaDesde;
        }
        if ($fechaHasta !== null && $fechaHasta !== '') {
            $where[] = 't.fecha<=:hasta';
            $params['hasta'] = $fechaHasta;
        }
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT t.*, a.cliente_id, a.caso_id, a.moneda, cl.nombre_razon_social AS cliente_nombre
             FROM trust_transactions t
             INNER JOIN trust_accounts a ON a.id=t.trust_account_id AND a.firma_id=t.firma_id
             INNER JOIN clientes cl ON cl.id=a.cliente_id AND cl.firma_id=a.firma_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY t.fecha ASC, t.id ASC'
        );
        $statement->execute($params);
        $saldo = 0.0;
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            $delta = $row['tipo'] === 'retiro' ? -((float) $row['monto']) : (float) $row['monto'];
            $saldo += $delta;
            $row['saldo_acumulado'] = number_format($saldo, 2, '.', '');
            $items[] = $row;
        }

        return ['items' => $items, 'saldo_final' => number_format($saldo, 2, '.', '')];
    }

    public function generarReporteConciliacion(int $firmaId, string $periodo): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $periodo) !== 1) {
            throw new HttpException(422, 'Periodo invalido.', ['periodo' => ['Formato esperado YYYY-MM']]);
        }
        $desde = $periodo . '-01';
        $hasta = date('Y-m-t', strtotime($desde));
        $libro = $this->getLibro($firmaId, null, $desde, $hasta);
        $pdf = $this->pdfs->fromTemplate('trust_reporte', ['periodo' => $periodo, 'libro' => $libro]);
        $key = $this->storage->upload($firmaId, 'trust/reportes/' . $periodo . '.pdf', $pdf, 'application/pdf');

        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'INSERT INTO trust_reportes (firma_id,periodo,s3_key,generado_at,generado_por_usuario_id)
             VALUES (:firma_id,:periodo,:s3_key,CURRENT_TIMESTAMP,:usuario_id)
             ON DUPLICATE KEY UPDATE s3_key=VALUES(s3_key), generado_at=VALUES(generado_at), generado_por_usuario_id=VALUES(generado_por_usuario_id)'
        );
        $statement->execute(['firma_id' => $firmaId, 'periodo' => $periodo, 's3_key' => $key, 'usuario_id' => $this->auth->id()]);

        return $key;
    }

    public function validarSeparacionFondos(int $firmaId, int $trustAccountId, int $clienteId): void
    {
        $account = $this->findAccount($firmaId, $trustAccountId);
        if ((int) $account['cliente_id'] !== $clienteId) {
            throw new HttpException(422, 'La cuenta trust no pertenece al cliente indicado.', ['cliente_id' => ['Separacion de fondos invalida']]);
        }
    }

    private function validateClienteCaso(int $firmaId, int $clienteId, ?int $casoId): void
    {
        if ($this->clientes->findForFirma($firmaId, $clienteId) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }
        if ($casoId !== null) {
            $case = $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
            if ((int) $case['cliente_id'] !== $clienteId) {
                throw new HttpException(422, 'El caso no pertenece al cliente seleccionado.');
            }
        }
    }

    private function accountForUpdate(int $firmaId, int $clienteId, ?int $casoId): array
    {
        $account = $this->findAccountByScopeForUpdate($firmaId, $clienteId, $casoId);
        if ($account !== null) {
            return $account;
        }
        // TENANT FILTER: firma_id = ?
        $insert = $this->pdo->prepare(
            'INSERT INTO trust_accounts (firma_id,cliente_id,caso_id,moneda,saldo,created_at,updated_at)
             VALUES (:firma_id,:cliente_id,:caso_id,\'COP\',0.00,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $insert->execute(['firma_id' => $firmaId, 'cliente_id' => $clienteId, 'caso_id' => $casoId]);

        return $this->findAccountForUpdate($firmaId, (int) $this->pdo->lastInsertId());
    }

    private function findAccountByScopeForUpdate(int $firmaId, int $clienteId, ?int $casoId): ?array
    {
        $lock = $this->forUpdate();
        $casoSql = $casoId === null ? 'caso_id IS NULL' : 'caso_id=:caso_id';
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT * FROM trust_accounts
             WHERE firma_id=:firma_id AND cliente_id=:cliente_id AND ' . $casoSql . ' AND moneda=\'COP\' AND deleted_at IS NULL
             LIMIT 1' . $lock
        );
        $params = ['firma_id' => $firmaId, 'cliente_id' => $clienteId];
        if ($casoId !== null) {
            $params['caso_id'] = $casoId;
        }
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function findAccountForUpdate(int $firmaId, int $trustAccountId): array
    {
        $lock = $this->forUpdate();
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'SELECT * FROM trust_accounts
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL
             LIMIT 1' . $lock
        );
        $statement->execute(['id' => $trustAccountId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : throw new HttpException(404, 'La cuenta trust no existe.');
    }

    private function findAccount(int $firmaId, int $trustAccountId): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare('SELECT * FROM trust_accounts WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL');
        $statement->execute(['id' => $trustAccountId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : throw new HttpException(404, 'La cuenta trust no existe.');
    }

    private function insertTransaction(int $firmaId, int $accountId, string $tipo, string $monto, string $descripcion, ?string $referencia): array
    {
        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'INSERT INTO trust_transactions
             (firma_id,trust_account_id,tipo,monto,descripcion,referencia,usuario_id,fecha,created_at)
             VALUES (:firma_id,:trust_account_id,:tipo,:monto,:descripcion,:referencia,:usuario_id,CURRENT_DATE,CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'trust_account_id' => $accountId,
            'tipo' => $tipo,
            'monto' => $monto,
            'descripcion' => $descripcion,
            'referencia' => $referencia === null ? null : mb_substr($referencia, 0, 100),
            'usuario_id' => $this->auth->id(),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        // TENANT FILTER: firma_id = ?
        $find = $this->pdo->prepare('SELECT * FROM trust_transactions WHERE id=:id AND firma_id=:firma_id');
        $find->execute(['id' => $id, 'firma_id' => $firmaId]);
        $row = $find->fetch();

        return is_array($row) ? $row : throw new \RuntimeException('No fue posible leer la transaccion trust creada.');
    }

    private function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            // @phpstan-ignore if.alwaysFalse
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function forUpdate(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function money(mixed $value): string
    {
        $amount = round((float) str_replace(',', '.', (string) $value), 2);
        if ($amount <= 0) {
            throw new HttpException(422, 'El monto debe ser mayor a cero.', ['monto' => ['Debe ser mayor a cero']]);
        }

        return number_format($amount, 2, '.', '');
    }

    private function description(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new HttpException(422, 'La descripcion es obligatoria.', ['descripcion' => ['La descripcion es obligatoria']]);
        }

        return mb_substr($value, 0, 2000);
    }
}
