<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Session;
use PDO;
use RuntimeException;

final class GoogleCalendarService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SensitiveDataService $sensitive,
        private readonly Session $session
    ) {
    }

    public function startOAuth(int $firmaId, int $userId): string
    {
        $state = bin2hex(random_bytes(24));
        $this->session->put('google_oauth_state', [
            'value' => hash('sha256', $state),
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'expires_at' => time() + 600,
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->requiredEnv('GOOGLE_CLIENT_ID'),
            'redirect_uri' => $this->requiredEnv('GOOGLE_REDIRECT_URI'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function handleCallback(int $firmaId, int $userId, string $code, string $state): void
    {
        $expected = $this->session->pull('google_oauth_state');
        if (
            !is_array($expected)
            || (int) ($expected['expires_at'] ?? 0) < time()
            || (int) ($expected['firma_id'] ?? 0) !== $firmaId
            || (int) ($expected['usuario_id'] ?? 0) !== $userId
            || !hash_equals((string) ($expected['value'] ?? ''), hash('sha256', $state))
        ) {
            throw new HttpException(400, 'El estado OAuth de Google no es valido o expiro.');
        }
        $token = $this->request('POST', 'https://oauth2.googleapis.com/token', [
            'client_id' => $this->requiredEnv('GOOGLE_CLIENT_ID'),
            'client_secret' => $this->requiredEnv('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => $this->requiredEnv('GOOGLE_REDIRECT_URI'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ], false);
        $this->saveToken($firmaId, $userId, $token);
    }

    /** @return array{created: int, skipped: int} */
    public function syncEvents(int $firmaId, int $userId): array
    {
        $token = $this->validAccessToken($firmaId, $userId);
        $statement = $this->pdo->prepare(
            'SELECT * FROM calendario_eventos
             WHERE firma_id=:firma_id AND created_by_usuario_id=:usuario_id
               AND external_id IS NULL AND deleted_at IS NULL ORDER BY inicio_at LIMIT 100'
        );
        $statement->execute(['firma_id' => $firmaId, 'usuario_id' => $userId]);
        $created = 0;
        $skipped = 0;
        foreach ($statement->fetchAll() as $event) {
            $response = $this->request(
                'POST',
                'https://www.googleapis.com/calendar/v3/calendars/primary/events',
                [
                    'summary' => (string) $event['titulo'],
                    'description' => (string) ($event['descripcion'] ?? ''),
                    'location' => (string) ($event['lugar'] ?? ''),
                    'start' => ['dateTime' => (new \DateTimeImmutable((string) $event['inicio_at']))->format(DATE_RFC3339)],
                    'end' => ['dateTime' => (new \DateTimeImmutable((string) $event['fin_at']))->format(DATE_RFC3339)],
                ],
                true,
                $token
            );
            $externalId = trim((string) ($response['id'] ?? ''));
            if ($externalId === '') {
                $skipped++;
                continue;
            }
            $update = $this->pdo->prepare(
                'UPDATE calendario_eventos SET external_id=:external_id,updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id AND firma_id=:firma_id'
            );
            $update->execute(['external_id' => $externalId, 'id' => (int) $event['id'], 'firma_id' => $firmaId]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function disconnect(int $firmaId, int $userId): void
    {
        $row = $this->integration($firmaId, $userId);
        if ($row !== null) {
            try {
                $token = $this->sensitive->decrypt((string) $row['access_token']);
                $this->request('POST', 'https://oauth2.googleapis.com/revoke?token=' . rawurlencode($token), [], false);
            } catch (\Throwable) {
            }
        }
        $statement = $this->pdo->prepare(
            'DELETE FROM integraciones_oauth WHERE firma_id=:firma_id AND usuario_id=:usuario_id AND proveedor=\'google_calendar\''
        );
        $statement->execute(['firma_id' => $firmaId, 'usuario_id' => $userId]);
    }

    private function validAccessToken(int $firmaId, int $userId): string
    {
        $row = $this->integration($firmaId, $userId) ?? throw new HttpException(409, 'Google Calendar no esta conectado.');
        if ($row['token_expires_at'] !== null && (string) $row['token_expires_at'] <= date('Y-m-d H:i:s', time() + 60)) {
            $refresh = $this->sensitive->decrypt((string) $row['refresh_token']);
            $token = $this->request('POST', 'https://oauth2.googleapis.com/token', [
                'client_id' => $this->requiredEnv('GOOGLE_CLIENT_ID'),
                'client_secret' => $this->requiredEnv('GOOGLE_CLIENT_SECRET'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
            ], false);
            $token['refresh_token'] = $refresh;
            $this->saveToken($firmaId, $userId, $token);
            return (string) $token['access_token'];
        }

        return $this->sensitive->decrypt((string) $row['access_token']);
    }

    /** @param array<string, mixed> $token */
    private function saveToken(int $firmaId, int $userId, array $token): void
    {
        if (empty($token['access_token'])) {
            throw new RuntimeException('Google no retorno un access token.');
        }
        $existing = $this->integration($firmaId, $userId);
        $refresh = (string) ($token['refresh_token'] ?? '');
        $encryptedRefresh = $refresh === ''
            ? ($existing['refresh_token'] ?? null)
            : $this->sensitive->encrypt($refresh);
        $statement = $this->pdo->prepare(
            'INSERT INTO integraciones_oauth
             (firma_id,usuario_id,proveedor,access_token,refresh_token,token_expires_at,scope,created_at,updated_at)
             VALUES (:firma_id,:usuario_id,\'google_calendar\',:access_token,:refresh_token,:expires,:scope,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE access_token=VALUES(access_token),refresh_token=VALUES(refresh_token),
                 token_expires_at=VALUES(token_expires_at),scope=VALUES(scope),updated_at=CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'usuario_id' => $userId,
            'access_token' => $this->sensitive->encrypt((string) $token['access_token']),
            'refresh_token' => $encryptedRefresh,
            'expires' => date('Y-m-d H:i:s', time() + max(60, (int) ($token['expires_in'] ?? 3600))),
            'scope' => (string) ($token['scope'] ?? 'https://www.googleapis.com/auth/calendar'),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function integration(int $firmaId, int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM integraciones_oauth
             WHERE firma_id=:firma_id AND usuario_id=:usuario_id AND proveedor=\'google_calendar\''
        );
        $statement->execute(['firma_id' => $firmaId, 'usuario_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function request(string $method, string $url, array $body, bool $json, ?string $bearer = null): array
    {
        $curl = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        if ($body !== []) {
            $payload = $json ? json_encode($body, JSON_THROW_ON_ERROR) : http_build_query($body);
            $headers[] = 'Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($raw) || $status >= 400) {
            throw new RuntimeException('Google API no disponible: ' . ($error ?: 'HTTP ' . $status));
        }
        $decoded = $raw === '' ? [] : json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function requiredEnv(string $key): string
    {
        $value = trim((string) Config::env($key, ''));
        if ($value === '') {
            throw new RuntimeException('Falta la variable ' . $key . '.');
        }

        return $value;
    }
}
