<?php

declare(strict_types=1);

namespace App\Jobs;

final class SendSmsJob extends Job
{
    public function handle(array $payload): void
    {
        // Stub intencional para conectar proveedor SMS en una fase posterior.
    }
}
