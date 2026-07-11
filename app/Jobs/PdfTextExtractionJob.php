<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\StorageService;
use PDO;
use Smalot\PdfParser\Parser;

final class PdfTextExtractionJob extends Job
{
    public function __construct(private readonly PDO $pdo, private readonly StorageService $storage)
    {
    }

    public function handle(array $payload): void
    {
        $firmaId = (int) ($payload['firma_id'] ?? 0);
        $documentId = (int) ($payload['documento_id'] ?? 0);
        $versionId = (int) ($payload['version_id'] ?? 0);
        $statement = $this->pdo->prepare(
            'SELECT * FROM documento_versiones
             WHERE id=:id AND documento_id=:documento_id AND firma_id=:firma_id AND extension=\'pdf\''
        );
        $statement->execute(['id' => $versionId, 'documento_id' => $documentId, 'firma_id' => $firmaId]);
        $version = $statement->fetch();
        if (!is_array($version)) {
            return;
        }
        $contents = null;
        if (!empty($version['s3_key'])) {
            $stream = $this->storage->download((string) $version['s3_key']);
            $contents = stream_get_contents($stream);
            fclose($stream);
        } else {
            $relative = (string) $version['storage_path'];
            if (str_contains($relative, '..')) {
                return;
            }
            $path = dirname(__DIR__, 2) . '/storage/' . str_replace('\\', '/', $relative);
            $contents = is_file($path) ? file_get_contents($path) : null;
        }
        if (!is_string($contents) || $contents === '') {
            return;
        }
        try {
            $text = trim((new Parser())->parseContent($contents)->getText());
        } catch (\Throwable) {
            $text = '';
        }
        $update = $this->pdo->prepare(
            'UPDATE documentos SET texto_extraido=:texto,texto_extraido_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $update->execute([
            'texto' => $text === '' ? null : mb_substr($text, 0, 100000),
            'id' => $documentId,
            'firma_id' => $firmaId,
        ]);
    }
}
