<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendEmailJob;
use PDO;
use chillerlan\QRCode\QRCode;

final class BillingService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdfService $pdf,
        private readonly StorageService $storage,
        private readonly QueueService $queue,
        private readonly AuditoriaService $audit,
        private readonly ?WebhookService $webhooks = null
    ) {
    }

    public function queuePdf(int $firmaId, int $honorarioId): void
    {
        $this->queue->dispatch(GenerateInvoicePdfJob::class, [
            'firma_id' => $firmaId,
            'honorario_id' => $honorarioId,
        ], 'billing');
    }

    public function generatePdf(int $firmaId, int $honorarioId): string
    {
        $invoice = $this->invoiceData($firmaId, $honorarioId);
        $binary = $this->pdf->fromTemplate('invoice', ['invoice' => $invoice]);
        $key = $this->storage->upload(
            $firmaId,
            'pdf/honorarios/' . $honorarioId . '-' . gmdate('YmdHis') . '.pdf',
            $binary,
            'application/pdf'
        );
        $statement = $this->pdo->prepare(
            'UPDATE honorarios SET pdf_s3_key=:key,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['key' => $key, 'id' => $honorarioId, 'firma_id' => $firmaId]);

        return $key;
    }

    /** @return array{pdf_url: string} */
    public function pdfUrl(int $firmaId, int $honorarioId): array
    {
        $invoice = $this->invoiceRow($firmaId, $honorarioId);
        $key = trim((string) ($invoice['pdf_s3_key'] ?? ''));
        if ($key === '') {
            $key = $this->generatePdf($firmaId, $honorarioId);
        }

        return ['pdf_url' => $this->storage->presignedUrl($key, 900)];
    }

    public function send(int $firmaId, int $honorarioId, ?string $email, ?Request $request = null): void
    {
        $invoice = $this->invoiceRow($firmaId, $honorarioId);
        $destination = trim((string) ($email ?? $invoice['cliente_email'] ?? ''));
        if (filter_var($destination, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'La factura requiere un email de destino valido.', ['email' => 'Email invalido o ausente.']);
        }
        $key = trim((string) ($invoice['pdf_s3_key'] ?? ''));
        if ($key === '') {
            $key = $this->generatePdf($firmaId, $honorarioId);
        }
        $token = $this->generatePaymentToken($firmaId, $honorarioId);
        $payUrl = rtrim((string) Config::get('app.url', ''), '/') . '/pay/' . $token;
        $subject = 'Factura #' . ($invoice['numero'] ?: $honorarioId) . ' - ' . $invoice['firma_nombre'];
        $body = '<p>Adjuntamos su factura por <strong>' . htmlspecialchars((string) $invoice['monto'])
            . ' ' . htmlspecialchars((string) $invoice['moneda']) . '</strong>.</p>'
            . '<p><a href="' . htmlspecialchars($payUrl) . '">Pagar factura</a></p>';
        $this->queue->dispatch(SendEmailJob::class, [
            'to' => $destination,
            'subject' => $subject,
            'body' => $body,
            'attachment_s3_key' => $key,
            'attachment_name' => 'factura-' . ($invoice['numero'] ?: $honorarioId) . '.pdf',
        ], 'mail');
        $update = $this->pdo->prepare(
            'UPDATE honorarios SET enviado_at=CURRENT_TIMESTAMP,enviado_a_email=:email,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $update->execute(['email' => strtolower($destination), 'id' => $honorarioId, 'firma_id' => $firmaId]);
        $this->audit->record('FACTURA_ENVIADA', 'finanzas', 'honorario', $honorarioId, [
            'email' => strtolower($destination),
        ], $request, $firmaId);
        $this->webhooks?->dispatch($firmaId, 'invoice.sent', [
            'id' => $honorarioId,
            'numero' => $invoice['numero'],
            'monto' => $invoice['monto'],
            'moneda' => $invoice['moneda'],
        ]);
    }

    public function generatePaymentToken(int $firmaId, int $honorarioId): string
    {
        $invoice = $this->invoiceRow($firmaId, $honorarioId);
        if (
            preg_match('/^[a-f0-9]{64}$/', (string) ($invoice['pago_token'] ?? '')) === 1
            && (string) ($invoice['pago_token_expires_at'] ?? '') > date('Y-m-d H:i:s')
        ) {
            return (string) $invoice['pago_token'];
        }
        $token = bin2hex(random_bytes(32));
        $statement = $this->pdo->prepare(
            'UPDATE honorarios SET pago_token=:token,pago_token_expires_at=:expires,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'token' => $token,
            'expires' => (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
            'id' => $honorarioId,
            'firma_id' => $firmaId,
        ]);

        return $token;
    }

    /** @return array<string, mixed> */
    public function publicInvoice(string $token): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new HttpException(404, 'El enlace de pago no es valido.');
        }
        $statement = $this->pdo->prepare(
            'SELECT h.id,h.firma_id,h.numero,h.concepto,h.monto,h.moneda,h.fecha_vencimiento,h.estado,
                    h.pago_token_expires_at,f.nombre AS firma_nombre,cl.nombre_razon_social AS cliente_nombre
             FROM honorarios h
             INNER JOIN firmas f ON f.id=h.firma_id
             INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id
             WHERE h.pago_token=:token AND h.deleted_at IS NULL'
        );
        $statement->execute(['token' => $token]);
        $invoice = $statement->fetch();
        if (!is_array($invoice)) {
            throw new HttpException(404, 'El enlace de pago no existe.');
        }
        if ((string) $invoice['pago_token_expires_at'] < date('Y-m-d H:i:s')) {
            throw new HttpException(410, 'Este enlace de pago ha expirado.');
        }
        if (in_array((string) $invoice['estado'], ['pagado', 'cobrado', 'cancelado', 'anulado'], true)) {
            throw new HttpException(409, 'Esta factura no admite nuevos pagos.');
        }

        return $invoice;
    }

    public function annul(int $firmaId, int $honorarioId, string $reason, int $userId, Request $request): void
    {
        $invoice = $this->invoiceRow($firmaId, $honorarioId);
        if (mb_strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'El motivo es obligatorio.', ['motivo' => 'Ingrese al menos 5 caracteres.']);
        }
        if ((float) $invoice['total_pagado'] > 0) {
            throw new HttpException(422, 'No se puede anular una factura con pagos registrados.');
        }
        $statement = $this->pdo->prepare(
            'UPDATE honorarios
             SET estado=\'anulado\',anulado_motivo=:motivo,anulado_at=CURRENT_TIMESTAMP,
                 anulado_por_usuario_id=:usuario_id,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute([
            'motivo' => mb_substr(trim($reason), 0, 2000),
            'usuario_id' => $userId,
            'id' => $honorarioId,
            'firma_id' => $firmaId,
        ]);
        $this->audit->record('FACTURA_ANULADA', 'finanzas', 'honorario', $honorarioId, [
            'estado_anterior' => $invoice['estado'],
            'motivo' => trim($reason),
        ], $request, $firmaId, 'warning');
    }

    /** @return array<string, mixed> */
    private function invoiceData(int $firmaId, int $honorarioId): array
    {
        $invoice = $this->invoiceRow($firmaId, $honorarioId);
        $lineStatement = $this->pdo->prepare(
            'SELECT descripcion,cantidad,tarifa,monto FROM honorario_lineas
             WHERE firma_id=:firma_id AND honorario_id=:honorario_id AND deleted_at IS NULL ORDER BY id'
        );
        $lineStatement->execute(['firma_id' => $firmaId, 'honorario_id' => $honorarioId]);
        $invoice['lineas'] = $lineStatement->fetchAll();
        $invoice['total'] = $invoice['monto'];
        $invoice['pago_url'] = $invoice['pago_token']
            ? rtrim((string) Config::get('app.url', ''), '/') . '/pay/' . $invoice['pago_token']
            : null;
        if (is_string($invoice['pago_url'])) {
            $invoice['qr_data_uri'] = (new QRCode())->render($invoice['pago_url']);
        }
        $logoKey = trim((string) ($invoice['logo_s3_key'] ?? ''));
        if ($logoKey !== '') {
            try {
                $stream = $this->storage->download($logoKey);
                $logo = stream_get_contents($stream);
                fclose($stream);
                if (is_string($logo)) {
                    $invoice['logo_data_uri'] = 'data:image/png;base64,' . base64_encode($logo);
                }
            } catch (\Throwable) {
                $invoice['logo_data_uri'] = null;
            }
        }

        return $invoice;
    }

    /** @return array<string, mixed> */
    private function invoiceRow(int $firmaId, int $honorarioId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT h.*,f.nombre AS firma_nombre,f.logo_s3_key,cl.nombre_razon_social AS cliente_nombre,
                    cl.email AS cliente_email,COALESCE(SUM(CASE WHEN p.estado=\'registrado\' THEN p.monto ELSE 0 END),0) AS total_pagado
             FROM honorarios h
             INNER JOIN firmas f ON f.id=h.firma_id
             INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id
             LEFT JOIN pagos p ON p.honorario_id=h.id AND p.firma_id=h.firma_id AND p.deleted_at IS NULL
             WHERE h.id=:id AND h.firma_id=:firma_id AND h.deleted_at IS NULL
             GROUP BY h.id,f.nombre,f.logo_s3_key,cl.nombre_razon_social,cl.email'
        );
        $statement->execute(['id' => $honorarioId, 'firma_id' => $firmaId]);
        $invoice = $statement->fetch();
        if (!is_array($invoice)) {
            throw new HttpException(404, 'La factura no existe en la firma.');
        }

        return $invoice;
    }
}
