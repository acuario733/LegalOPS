<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PdfService;
use App\Services\StorageService;
use PDO;

final class GeneratePdfJob extends Job
{
    public function __construct(
        private readonly PdfService $pdfs,
        private readonly StorageService $storage,
        private readonly PDO $pdo
    ) {
    }

    public function handle(array $payload): void
    {
        $firmaId = (int) ($payload['firma_id'] ?? 0);
        $honorarioId = (int) ($payload['honorario_id'] ?? 0);
        $template = (string) ($payload['template'] ?? 'invoice');
        $pdf = $this->pdfs->fromTemplate($template, $payload['data'] ?? []);
        $key = $this->storage->upload($firmaId, 'pdf/honorarios/' . $honorarioId . '.pdf', $pdf, 'application/pdf');

        // TENANT FILTER: firma_id = ?
        $statement = $this->pdo->prepare(
            'UPDATE honorarios SET pdf_s3_key=:pdf_s3_key, updated_at=CURRENT_TIMESTAMP(6)
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['pdf_s3_key' => $key, 'id' => $honorarioId, 'firma_id' => $firmaId]);
    }
}
