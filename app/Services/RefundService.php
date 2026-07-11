<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use PDO;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

final class RefundService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AuditoriaService $audit,
        private readonly DashboardService $dashboard
    ) {
    }

    public function refund(int $firmaId, int $paymentId, ?float $amount, string $reason, Request $request): void
    {
        if (mb_strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'El motivo del reembolso es obligatorio.', ['motivo' => 'Ingrese al menos 5 caracteres.']);
        }
        $statement = $this->pdo->prepare(
            'SELECT * FROM pagos WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $paymentId, 'firma_id' => $firmaId]);
        $payment = $statement->fetch();
        if (!is_array($payment)) {
            throw new HttpException(404, 'El pago no existe en la firma.');
        }
        $refundAmount = $amount ?? (float) $payment['monto'];
        if ($refundAmount <= 0 || $refundAmount > (float) $payment['monto']) {
            throw new HttpException(422, 'El monto del reembolso no es valido.');
        }
        $refundId = null;
        if (trim((string) ($payment['stripe_charge_id'] ?? '')) !== '') {
            try {
                $client = new StripeClient((string) Config::env('STRIPE_SECRET', ''));
                $refund = $client->refunds->create([
                    'charge' => (string) $payment['stripe_charge_id'],
                    'amount' => (int) round($refundAmount * 100),
                    'metadata' => ['pago_id' => (string) $paymentId, 'firma_id' => (string) $firmaId],
                ]);
                $refundId = $refund->id;
            } catch (InvalidRequestException $exception) {
                throw new HttpException(422, 'Stripe rechazo el reembolso: ' . $exception->getMessage());
            }
        }
        $total = $refundAmount + (float) ($payment['reembolso_monto'] ?? 0);
        $full = $total + 0.00001 >= (float) $payment['monto'];
        $update = $this->pdo->prepare(
            'UPDATE pagos SET reembolsado_at=CURRENT_TIMESTAMP,reembolso_monto=:monto,stripe_refund_id=:refund_id,
             estado=:estado,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND firma_id=:firma_id'
        );
        $update->execute([
            'monto' => number_format($total, 2, '.', ''),
            'refund_id' => $refundId,
            'estado' => $full ? 'reembolsado' : 'registrado',
            'id' => $paymentId,
            'firma_id' => $firmaId,
        ]);
        if ($full && $payment['honorario_id'] !== null) {
            $invoice = $this->pdo->prepare(
                'UPDATE honorarios SET estado=\'pendiente\',stripe_status=\'refunded\',updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
            );
            $invoice->execute(['id' => (int) $payment['honorario_id'], 'firma_id' => $firmaId]);
        }
        $this->audit->record('PAGO_REEMBOLSADO', 'finanzas', 'pago', $paymentId, [
            'monto' => $refundAmount,
            'total' => $full,
            'motivo' => trim($reason),
            'stripe_refund_id' => $refundId,
        ], $request, $firmaId, 'warning');
        $this->dashboard->invalidateKpis($firmaId);
    }
}
