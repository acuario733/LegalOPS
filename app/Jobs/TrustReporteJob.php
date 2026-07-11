<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TrustService;

final class TrustReporteJob extends Job
{
    public function __construct(private readonly TrustService $trust)
    {
    }

    public function handle(array $payload): void
    {
        $firmaId = (int) ($payload['firma_id'] ?? 0);
        $periodo = (string) ($payload['periodo'] ?? date('Y-m', strtotime('first day of previous month')));
        $this->trust->generarReporteConciliacion($firmaId, $periodo);
    }
}
