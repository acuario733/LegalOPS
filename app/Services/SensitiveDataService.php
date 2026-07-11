<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class SensitiveDataService
{
    public function encrypt(string $value): string
    {
        $key = $this->encryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($ciphertext)) {
            throw new RuntimeException('No fue posible cifrar el dato sensible.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if (!is_string($decoded) || strlen($decoded) < 29) {
            throw new RuntimeException('El dato cifrado no es valido.');
        }
        $value = openssl_decrypt(
            substr($decoded, 28),
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            substr($decoded, 0, 12),
            substr($decoded, 12, 16)
        );
        if (!is_string($value)) {
            throw new RuntimeException('No fue posible descifrar el dato sensible.');
        }

        return $value;
    }

    public function maskDocument(?string $value): ?string
    {
        return $this->mask($value, 2, 2);
    }

    public function maskProfessionalCard(?string $value): ?string
    {
        return $this->mask($value, 2, 3);
    }

    public function hash(?string $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return hash('sha256', mb_strtoupper($normalized));
    }

    private function mask(?string $value, int $visibleStart, int $visibleEnd): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }

        $length = mb_strlen($clean);
        if ($length <= ($visibleStart + $visibleEnd)) {
            return mb_substr($clean, 0, 1) . str_repeat('*', max(1, $length - 1));
        }

        return mb_substr($clean, 0, $visibleStart)
            . str_repeat('*', max(4, $length - $visibleStart - $visibleEnd))
            . mb_substr($clean, -$visibleEnd);
    }

    private function encryptionKey(): string
    {
        $configured = trim((string) Config::env('APP_ENCRYPTION_KEY', ''));
        if ($configured === '') {
            throw new RuntimeException('Falta APP_ENCRYPTION_KEY para cifrar integraciones.');
        }

        return hash('sha256', $configured, true);
    }
}
