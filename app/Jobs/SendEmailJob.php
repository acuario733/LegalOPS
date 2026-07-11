<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LocalMailService;
use App\Services\StorageService;

final class SendEmailJob extends Job
{
    public function __construct(
        private readonly LocalMailService $mail,
        private readonly StorageService $storage
    ) {
    }

    public function handle(array $payload): void
    {
        $attachments = [];
        $key = trim((string) ($payload['attachment_s3_key'] ?? ''));
        if ($key !== '') {
            $stream = $this->storage->download($key);
            $contents = stream_get_contents($stream);
            fclose($stream);
            if (!is_string($contents)) {
                throw new \RuntimeException('No fue posible leer el adjunto del correo.');
            }
            $attachments[] = [
                'name' => (string) ($payload['attachment_name'] ?? 'documento.pdf'),
                'mime' => 'application/pdf',
                'content' => $contents,
            ];
        }
        $this->mail->send(
            (string) ($payload['to'] ?? ''),
            (string) ($payload['subject'] ?? 'LegalOPS Cloud'),
            (string) ($payload['body'] ?? ''),
            $attachments
        );
    }
}
