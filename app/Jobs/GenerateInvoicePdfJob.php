<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BillingService;

final class GenerateInvoicePdfJob extends Job
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function handle(array $payload): void
    {
        $this->billing->generatePdf((int) ($payload['firma_id'] ?? 0), (int) ($payload['honorario_id'] ?? 0));
    }
}
