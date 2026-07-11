<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BillingService;
use App\Services\WebhookService;
use PDO;

final class InvoiceReminderJob extends Job
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly BillingService $billing,
        private readonly WebhookService $webhooks
    ) {
    }

    public function handle(array $payload): void
    {
        $where = 'h.estado=\'pendiente\' AND h.fecha_vencimiento<CURRENT_DATE
                  AND (h.ultimo_recordatorio_at IS NULL OR h.ultimo_recordatorio_at<DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 7 DAY))
                  AND h.deleted_at IS NULL';
        $params = [];
        if (($payload['firma_id'] ?? null) !== null) {
            $where .= ' AND h.firma_id=:firma_id';
            $params['firma_id'] = (int) $payload['firma_id'];
        }
        $statement = $this->pdo->prepare('SELECT h.id,h.firma_id,cl.email FROM honorarios h INNER JOIN clientes cl ON cl.id=h.cliente_id AND cl.firma_id=h.firma_id WHERE ' . $where);
        $statement->execute($params);
        foreach ($statement->fetchAll() as $invoice) {
            $this->billing->send((int) $invoice['firma_id'], (int) $invoice['id'], (string) $invoice['email']);
            $update = $this->pdo->prepare('UPDATE honorarios SET ultimo_recordatorio_at=CURRENT_TIMESTAMP WHERE id=:id AND firma_id=:firma_id');
            $update->execute(['id' => (int) $invoice['id'], 'firma_id' => (int) $invoice['firma_id']]);
            $this->webhooks->dispatch((int) $invoice['firma_id'], 'invoice.overdue', ['id' => (int) $invoice['id']]);
        }
    }
}
