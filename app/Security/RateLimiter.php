<?php

declare(strict_types=1);

namespace App\Security;

/**
 * RateLimiter en memoria por proceso (para tests y entornos de un solo proceso).
 * Para producción con múltiples procesos, persistir en Redis o BD.
 */
final class RateLimiter
{
    /** @var array<string, list<int>> */
    private array $attempts = [];

    /**
     * Verifica si el cliente puede realizar la acción.
     *
     * @param string $clientId  Identificador único (user ID o IP)
     * @param string $endpoint  Nombre del endpoint
     * @param int    $limit     Máximo de intentos en la ventana
     * @param int    $windowSeconds Ventana de tiempo en segundos
     */
    public function allow(string $clientId, string $endpoint, int $limit = 60, int $windowSeconds = 60): bool
    {
        $key = $this->makeKey($clientId, $endpoint);
        $now = time();
        $windowStart = $now - $windowSeconds;

        $this->attempts[$key] = array_values(array_filter(
            $this->attempts[$key] ?? [],
            static fn (int $ts): bool => $ts > $windowStart,
        ));

        if (count($this->attempts[$key]) >= $limit) {
            return false;
        }

        $this->attempts[$key][] = $now;

        return true;
    }

    /** Limpia el contador para el cliente (útil tras verificación de email, etc.). */
    public function reset(string $clientId, string $endpoint): void
    {
        unset($this->attempts[$this->makeKey($clientId, $endpoint)]);
    }

    /** Devuelve el número de intentos en la ventana por defecto de 60 s. */
    public function getAttempts(string $clientId, string $endpoint, int $windowSeconds = 60): int
    {
        $key = $this->makeKey($clientId, $endpoint);
        $cutoff = time() - $windowSeconds;

        return count(array_filter(
            $this->attempts[$key] ?? [],
            static fn (int $ts): bool => $ts > $cutoff,
        ));
    }

    /** Devuelve los segundos que faltan para poder reintentar. */
    public function getRetryAfter(string $clientId, string $endpoint, int $windowSeconds = 60): int
    {
        $key = $this->makeKey($clientId, $endpoint);
        $timestamps = $this->attempts[$key] ?? [];

        if ($timestamps === []) {
            return 0;
        }

        $oldest = min($timestamps);

        return max(0, $oldest + $windowSeconds - time());
    }

    public function allowPersistent(
        string $clientId,
        string $endpoint,
        int $limit = 60,
        int $windowSeconds = 60
    ): bool {
        $path = $this->persistentPath($clientId, $endpoint);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return $this->allow($clientId, $endpoint, $limit, $windowSeconds);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return $this->allow($clientId, $endpoint, $limit, $windowSeconds);
            }
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $now = time();
            $windowStart = $now - $windowSeconds;
            $attempts = array_values(array_filter(
                is_array($decoded) ? $decoded : [],
                static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $windowStart
            ));
            $allowed = count($attempts) < $limit;
            if ($allowed) {
                $attempts[] = $now;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($attempts, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $allowed;
        } finally {
            fclose($handle);
        }
    }

    public function getPersistentRetryAfter(
        string $clientId,
        string $endpoint,
        int $windowSeconds = 60
    ): int {
        $path = $this->persistentPath($clientId, $endpoint);
        if (!is_file($path)) {
            return 0;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        $attempts = array_values(array_filter(
            is_array($decoded) ? $decoded : [],
            static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > time() - $windowSeconds
        ));

        return $attempts === [] ? 0 : max(0, min($attempts) + $windowSeconds - time());
    }

    private function makeKey(string $clientId, string $endpoint): string
    {
        return $clientId . ':' . $endpoint;
    }

    private function persistentPath(string $clientId, string $endpoint): string
    {
        $directory = dirname(__DIR__, 2) . '/storage/locks/rate-limit';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('No fue posible preparar el almacenamiento del rate limit.');
        }

        return $directory . '/' . hash('sha256', $this->makeKey($clientId, $endpoint)) . '.json';
    }
}
