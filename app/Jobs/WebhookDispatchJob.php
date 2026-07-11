<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SensitiveDataService;
use PDO;
use RuntimeException;

final class WebhookDispatchJob extends Job
{
    public function __construct(private readonly PDO $pdo, private readonly SensitiveDataService $sensitive)
    {
    }

    public function handle(array $payload): void
    {
        $firmaId = (int) ($payload['firma_id'] ?? 0);
        $webhookId = (int) ($payload['webhook_id'] ?? 0);
        $attempt = max(1, (int) ($payload['_attempt'] ?? 1));
        $statement = $this->pdo->prepare(
            'SELECT url,secret_encrypted FROM webhooks
             WHERE id=:id AND firma_id=:firma_id AND activo=1 AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $webhookId, 'firma_id' => $firmaId]);
        $webhook = $statement->fetch();
        if (!is_array($webhook) || empty($webhook['secret_encrypted'])) {
            return;
        }
        $json = json_encode($payload['payload'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = 'sha256=' . hash_hmac('sha256', $json, $this->sensitive->decrypt((string) $webhook['secret_encrypted']));
        $curl = curl_init((string) $webhook['url']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-LegalOPS-Signature: ' . $signature,
                'X-LegalOPS-Event: ' . (string) ($payload['evento'] ?? ''),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $success = is_string($response) && $status >= 200 && $status < 300;
        $delivery = $this->pdo->prepare(
            'INSERT INTO webhook_entregas
             (firma_id,webhook_id,evento,payload,status_http,respuesta,intento,entregado_at,error,created_at)
             VALUES (:firma_id,:webhook_id,:evento,:payload,:status_http,:respuesta,:intento,:entregado_at,:error,CURRENT_TIMESTAMP)'
        );
        $delivery->execute([
            'firma_id' => $firmaId,
            'webhook_id' => $webhookId,
            'evento' => mb_substr((string) ($payload['evento'] ?? ''), 0, 100),
            'payload' => $json,
            'status_http' => $status > 0 ? $status : null,
            'respuesta' => is_string($response) ? mb_substr($response, 0, 2000) : null,
            'intento' => $attempt,
            'entregado_at' => $success ? date('Y-m-d H:i:s') : null,
            'error' => $success ? null : mb_substr($error !== '' ? $error : 'HTTP ' . $status, 0, 2000),
        ]);
        if (!$success) {
            throw new RuntimeException($error !== '' ? $error : 'Webhook respondio HTTP ' . $status);
        }
    }

    public function maxAttempts(): int
    {
        return 4;
    }

    public function retryDelay(int $failedAttempt): int
    {
        return [1 => 60, 2 => 300, 3 => 1800][$failedAttempt] ?? 1800;
    }
}
