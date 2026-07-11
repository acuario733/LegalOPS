<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use PDO;
use RuntimeException;

final class DocuSignService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SensitiveDataService $sensitive,
        private readonly StorageService $storage,
        private readonly DocumentoVersionService $versions,
        private readonly Session $session
    ) {
    }

    public function startOAuth(int $firmaId, int $userId): string
    {
        $state = bin2hex(random_bytes(24));
        $this->session->put('docusign_oauth_state', [
            'hash' => hash('sha256', $state),
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'expires_at' => time() + 600,
        ]);

        return rtrim($this->env('DOCUSIGN_OAUTH_BASE_URL'), '/') . '/oauth/auth?' . http_build_query([
            'response_type' => 'code',
            'scope' => 'signature',
            'client_id' => $this->env('DOCUSIGN_CLIENT_ID'),
            'redirect_uri' => $this->env('DOCUSIGN_REDIRECT_URI'),
            'state' => $state,
        ]);
    }

    public function handleCallback(int $firmaId, int $userId, string $code, string $state): void
    {
        $expected = $this->session->pull('docusign_oauth_state');
        if (
            !is_array($expected)
            || (int) ($expected['expires_at'] ?? 0) < time()
            || (int) ($expected['firma_id'] ?? 0) !== $firmaId
            || (int) ($expected['usuario_id'] ?? 0) !== $userId
            || !hash_equals((string) ($expected['hash'] ?? ''), hash('sha256', $state))
        ) {
            throw new HttpException(400, 'El estado OAuth de DocuSign no es valido o expiro.');
        }
        $basic = base64_encode($this->env('DOCUSIGN_CLIENT_ID') . ':' . $this->env('DOCUSIGN_CLIENT_SECRET'));
        $token = $this->requestJson(
            'POST',
            rtrim($this->env('DOCUSIGN_OAUTH_BASE_URL'), '/') . '/oauth/token',
            ['grant_type' => 'authorization_code', 'code' => $code],
            ['Authorization: Basic ' . $basic],
            false
        );
        $this->saveToken($firmaId, $userId, $token);
    }

    /** @param list<array<string, mixed>> $signers */
    public function createEnvelope(int $firmaId, int $documentId, array $signers): string
    {
        if ($signers === []) {
            throw new HttpException(422, 'Debe indicar al menos un firmante.');
        }
        $document = $this->document($firmaId, $documentId);
        $version = $this->currentVersion($firmaId, $documentId);
        $bytes = $this->versionContents($version);
        $recipients = [];
        foreach ($signers as $index => $signer) {
            $email = trim((string) ($signer['email'] ?? ''));
            $name = trim((string) ($signer['nombre'] ?? ''));
            if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException(422, 'Cada firmante requiere nombre y email validos.');
            }
            $recipients[] = [
                'email' => $email,
                'name' => $name,
                'recipientId' => (string) ($index + 1),
                'routingOrder' => (string) ($index + 1),
                'tabs' => ['signHereTabs' => [['anchorString' => '/firma' . ($index + 1) . '/', 'anchorUnits' => 'pixels']]],
            ];
        }
        $token = $this->accessToken($firmaId);
        $accountId = $this->env('DOCUSIGN_ACCOUNT_ID');
        $response = $this->requestJson(
            'POST',
            rtrim($this->env('DOCUSIGN_BASE_URL'), '/') . '/restapi/v2.1/accounts/' . rawurlencode($accountId) . '/envelopes',
            [
                'emailSubject' => 'Documento para firma: ' . (string) $document['titulo'],
                'documents' => [[
                    'documentBase64' => base64_encode($bytes),
                    'name' => (string) $version['nombre_original'],
                    'fileExtension' => (string) $version['extension'],
                    'documentId' => '1',
                ]],
                'recipients' => ['signers' => $recipients],
                'status' => 'sent',
            ],
            ['Authorization: Bearer ' . $token],
            true
        );
        $envelopeId = trim((string) ($response['envelopeId'] ?? ''));
        if ($envelopeId === '') {
            throw new RuntimeException('DocuSign no retorno envelopeId.');
        }
        $update = $this->pdo->prepare(
            'UPDATE documentos SET firma_estado=\'enviado\',docusign_envelope_id=:envelope_id,
             firma_solicitada_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $update->execute(['envelope_id' => $envelopeId, 'id' => $documentId, 'firma_id' => $firmaId]);

        return $envelopeId;
    }

    /** @return array{envelope_id: ?string,estado: string,solicitada_at: mixed,completada_at: mixed} */
    public function status(int $firmaId, int $documentId): array
    {
        $document = $this->document($firmaId, $documentId);

        return [
            'envelope_id' => $document['docusign_envelope_id'] ?? null,
            'estado' => (string) ($document['firma_estado'] ?? 'sin_firma'),
            'solicitada_at' => $document['firma_solicitada_at'] ?? null,
            'completada_at' => $document['firma_completada_at'] ?? null,
        ];
    }

    public function handleWebhook(string $payload, string $signature, Request $request): void
    {
        $secret = $this->env('DOCUSIGN_WEBHOOK_SECRET');
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        if ($signature === '' || !hash_equals($expected, $signature)) {
            throw new HttpException(400, 'Firma de webhook DocuSign invalida.');
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new HttpException(400, 'Payload DocuSign invalido.');
        }
        $envelopeId = (string) ($event['data']['envelopeId'] ?? $event['envelopeId'] ?? '');
        $status = strtolower((string) ($event['data']['envelopeSummary']['status'] ?? $event['status'] ?? ''));
        $mapped = match ($status) {
            'completed' => 'completado',
            'declined', 'voided' => 'rechazado',
            'sent', 'delivered' => 'enviado',
            default => 'pendiente',
        };
        $statement = $this->pdo->prepare(
            'SELECT id,firma_id FROM documentos WHERE docusign_envelope_id=:envelope_id AND deleted_at IS NULL'
        );
        $statement->execute(['envelope_id' => $envelopeId]);
        $document = $statement->fetch();
        if (!is_array($document)) {
            return;
        }
        $update = $this->pdo->prepare(
            'UPDATE documentos SET firma_estado=:estado,
             firma_completada_at=CASE WHEN :estado_completed=\'completado\' THEN CURRENT_TIMESTAMP ELSE firma_completada_at END,
             updated_at=CURRENT_TIMESTAMP WHERE id=:id AND firma_id=:firma_id'
        );
        $update->execute([
            'estado' => $mapped,
            'estado_completed' => $mapped,
            'id' => (int) $document['id'],
            'firma_id' => (int) $document['firma_id'],
        ]);
        if ($mapped === 'completado') {
            $this->storeCompletedDocument((int) $document['firma_id'], (int) $document['id'], $envelopeId, $request);
        }
    }

    private function storeCompletedDocument(int $firmaId, int $documentId, string $envelopeId, Request $request): void
    {
        $token = $this->accessToken($firmaId);
        $url = rtrim($this->env('DOCUSIGN_BASE_URL'), '/') . '/restapi/v2.1/accounts/'
            . rawurlencode($this->env('DOCUSIGN_ACCOUNT_ID')) . '/envelopes/' . rawurlencode($envelopeId)
            . '/documents/combined';
        $pdf = $this->requestRaw('GET', $url, ['Authorization: Bearer ' . $token]);
        $this->versions->createFromContents(
            $firmaId,
            $documentId,
            'documento-firmado-' . $envelopeId . '.pdf',
            'application/pdf',
            $pdf,
            $request,
            'DOCUMENTO_FIRMA_COMPLETADA'
        );
    }

    /** @return array<string, mixed> */
    private function document(int $firmaId, int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM documentos WHERE id=:id AND firma_id=:firma_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $documentId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : throw new HttpException(404, 'El documento no existe en la firma.');
    }

    /** @return array<string, mixed> */
    private function currentVersion(int $firmaId, int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT v.* FROM documentos d INNER JOIN documento_versiones v
               ON v.id=d.current_version_id AND v.firma_id=d.firma_id
             WHERE d.id=:id AND d.firma_id=:firma_id AND d.deleted_at IS NULL'
        );
        $statement->execute(['id' => $documentId, 'firma_id' => $firmaId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : throw new HttpException(409, 'El documento no tiene version para firmar.');
    }

    /** @param array<string, mixed> $version */
    private function versionContents(array $version): string
    {
        if (!empty($version['s3_key'])) {
            $stream = $this->storage->download((string) $version['s3_key']);
            $contents = stream_get_contents($stream);
            fclose($stream);
        } else {
            $relative = (string) $version['storage_path'];
            if (str_contains($relative, '..')) {
                throw new HttpException(404, 'La version no esta disponible.');
            }
            $contents = file_get_contents(dirname(__DIR__, 2) . '/storage/' . $relative);
        }
        if (!is_string($contents)) {
            throw new RuntimeException('No fue posible leer el documento.');
        }

        return $contents;
    }

    private function accessToken(int $firmaId): string
    {
        $statement = $this->pdo->prepare(
            'SELECT access_token FROM integraciones_oauth
             WHERE firma_id=:firma_id AND proveedor=\'docusign\' ORDER BY updated_at DESC LIMIT 1'
        );
        $statement->execute(['firma_id' => $firmaId]);
        $token = $statement->fetchColumn();
        if (!is_string($token)) {
            throw new HttpException(409, 'DocuSign no esta conectado para la firma.');
        }

        return $this->sensitive->decrypt($token);
    }

    /** @param array<string, mixed> $token */
    private function saveToken(int $firmaId, int $userId, array $token): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO integraciones_oauth
             (firma_id,usuario_id,proveedor,access_token,refresh_token,token_expires_at,scope,created_at,updated_at)
             VALUES (:firma_id,:usuario_id,\'docusign\',:access_token,:refresh_token,:expires,\'signature\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE access_token=VALUES(access_token),refresh_token=VALUES(refresh_token),
               token_expires_at=VALUES(token_expires_at),updated_at=CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'access_token' => $this->sensitive->encrypt((string) ($token['access_token'] ?? '')),
            'refresh_token' => isset($token['refresh_token']) ? $this->sensitive->encrypt((string) $token['refresh_token']) : null,
            'expires' => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)),
        ]);
    }

    /** @param array<string, mixed> $body @param list<string> $headers @return array<string, mixed> */
    private function requestJson(string $method, string $url, array $body, array $headers, bool $json): array
    {
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded');
        $payload = $json ? json_encode($body, JSON_THROW_ON_ERROR) : http_build_query($body);
        $raw = $this->requestRaw($method, $url, $headers, $payload);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param list<string> $headers */
    private function requestRaw(string $method, string $url, array $headers, ?string $body = null): string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($raw) || $status >= 400) {
            throw new RuntimeException('DocuSign no disponible: ' . ($error ?: 'HTTP ' . $status));
        }

        return $raw;
    }

    private function env(string $key): string
    {
        $value = trim((string) Config::env($key, ''));
        if ($value === '') {
            throw new RuntimeException('Falta la variable ' . $key . '.');
        }

        return $value;
    }
}
