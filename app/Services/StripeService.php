<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Repositories\NotificacionRepository;
use PDO;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

final class StripeService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly BillingService $billing,
        private readonly NotificacionRepository $notifications,
        private readonly AuditoriaService $audit,
        private readonly DashboardService $dashboard,
        private readonly ?WebhookService $webhooks = null
    ) {
    }

    /** @return array{client_secret: string, payment_intent_id: string} */
    public function createPaymentIntent(float $amount, string $currency, int $honorarioId, int $firmaId): array
    {
        if ($amount <= 0) {
            throw new HttpException(422, 'El monto del pago debe ser mayor a cero.');
        }
        $intent = $this->client()->paymentIntents->create([
            'amount' => (int) round($amount * 100),
            'currency' => strtolower($currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => ['honorario_id' => (string) $honorarioId, 'firma_id' => (string) $firmaId],
        ], ['idempotency_key' => 'honorario-' . $firmaId . '-' . $honorarioId]);
        $statement = $this->pdo->prepare(
            'UPDATE honorarios SET stripe_payment_intent_id=:intent,stripe_status=:status,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'intent' => $intent->id,
            'status' => $intent->status,
            'id' => $honorarioId,
            'firma_id' => $firmaId,
        ]);

        return ['client_secret' => (string) $intent->client_secret, 'payment_intent_id' => $intent->id];
    }

    /** @return array{client_secret: string, payment_intent_id: string} */
    public function payFromToken(string $token): array
    {
        $invoice = $this->billing->publicInvoice($token);

        return $this->createPaymentIntent(
            (float) $invoice['monto'],
            (string) $invoice['moneda'],
            (int) $invoice['id'],
            (int) $invoice['firma_id']
        );
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        $secret = trim((string) Config::env('STRIPE_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            throw new \RuntimeException('Falta STRIPE_WEBHOOK_SECRET.');
        }
        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException | SignatureVerificationException $exception) {
            throw new HttpException(400, 'Firma de webhook Stripe invalida.');
        }
        if ($event->type === 'payment_intent.succeeded') {
            $this->confirmPayment($event->data->object);
        }
    }

    private function confirmPayment(object $intent): void
    {
        $intentId = (string) ($intent->id ?? '');
        $statement = $this->pdo->prepare(
            'SELECT h.*,c.responsable_usuario_id
             FROM honorarios h LEFT JOIN casos c ON c.id=h.caso_id AND c.firma_id=h.firma_id
             WHERE h.stripe_payment_intent_id=:intent AND h.deleted_at IS NULL'
        );
        $statement->execute(['intent' => $intentId]);
        $invoice = $statement->fetch();
        if (!is_array($invoice)) {
            return;
        }
        $chargeId = (string) ($intent->latest_charge ?? '');
        $duplicate = $this->pdo->prepare(
            'SELECT id FROM pagos WHERE firma_id=:firma_id AND honorario_id=:honorario_id
             AND stripe_charge_id=:charge_id AND deleted_at IS NULL'
        );
        $duplicate->execute([
            'firma_id' => (int) $invoice['firma_id'],
            'honorario_id' => (int) $invoice['id'],
            'charge_id' => $chargeId,
        ]);
        if ($duplicate->fetchColumn() !== false) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO pagos
                 (firma_id,cliente_id,caso_id,honorario_id,fecha_pago,monto,moneda,metodo_pago,referencia,
                  referencia_hash,stripe_charge_id,estado,observaciones,created_at,updated_at)
                 VALUES (:firma_id,:cliente_id,:caso_id,:honorario_id,CURRENT_DATE,:monto,:moneda,\'stripe\',
                         :referencia,:referencia_hash,:charge_id,\'registrado\',\'Pago confirmado por webhook Stripe\',
                         CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
            );
            $insert->execute([
                'firma_id' => (int) $invoice['firma_id'],
                'cliente_id' => (int) $invoice['cliente_id'],
                'caso_id' => $invoice['caso_id'],
                'honorario_id' => (int) $invoice['id'],
                'monto' => number_format(((int) ($intent->amount_received ?? 0)) / 100, 2, '.', ''),
                'moneda' => strtoupper((string) ($intent->currency ?? $invoice['moneda'])),
                'referencia' => $intentId,
                'referencia_hash' => hash('sha256', $intentId),
                'charge_id' => $chargeId,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();
            $update = $this->pdo->prepare(
                'UPDATE honorarios SET estado=\'pagado\',stripe_status=\'succeeded\',updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
            );
            $update->execute(['id' => (int) $invoice['id'], 'firma_id' => (int) $invoice['firma_id']]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        $recipient = (int) ($invoice['responsable_usuario_id'] ?? 0);
        if ($recipient <= 0) {
            $recipient = $this->notifications->fallbackUsers((int) $invoice['firma_id'])[0] ?? 0;
        }
        if ($recipient > 0) {
            $this->notifications->createIfMissing([
                'firma_id' => (int) $invoice['firma_id'],
                'usuario_id' => $recipient,
                'titulo' => 'Factura pagada',
                'mensaje' => 'Stripe confirmo el pago de la factura ' . ($invoice['numero'] ?: $invoice['id']) . '.',
                'severidad' => 'info',
                'origen_tipo' => 'pago',
                'origen_id' => $paymentId,
                'origen_url' => '/finanzas/pagos',
                'dedupe_key' => hash('sha256', 'stripe|' . $intentId . '|' . $recipient),
            ]);
        }
        $this->audit->record('PAGO_STRIPE_CONFIRMADO', 'finanzas', 'pago', $paymentId, [
            'payment_intent_id' => $intentId,
            'honorario_id' => (int) $invoice['id'],
        ], null, (int) $invoice['firma_id'], 'warning');
        $this->dashboard->invalidateKpis((int) $invoice['firma_id']);
        $this->webhooks?->dispatch((int) $invoice['firma_id'], 'invoice.paid', [
            'id' => (int) $invoice['id'],
            'pago_id' => $paymentId,
            'payment_intent_id' => $intentId,
        ]);
        $this->webhooks?->dispatch((int) $invoice['firma_id'], 'payment.received', [
            'id' => $paymentId,
            'honorario_id' => (int) $invoice['id'],
        ]);
    }

    private function client(): StripeClient
    {
        $secret = trim((string) Config::env('STRIPE_SECRET', ''));
        if ($secret === '') {
            throw new \RuntimeException('Falta STRIPE_SECRET.');
        }

        return $this->client ??= new StripeClient($secret);
    }
}
