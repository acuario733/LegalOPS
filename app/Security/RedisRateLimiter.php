<?php

declare(strict_types=1);

namespace App\Security;

use Predis\Client as RedisClient;
use Predis\Connection\ConnectionException;
use Throwable;

/**
 * RateLimiter respaldado por Redis (sliding window counter).
 *
 * Usa INCR + EXPIRE de Redis para conteo atómico y thread-safe.
 * Si Redis no está disponible, falla abierto (permite el request) y registra el warning.
 *
 * Clave Redis: "rate_limit:{clientId}:{endpoint}"
 */
final class RedisRateLimiter
{
    private const KEY_PREFIX = 'rate_limit:';

    public function __construct(
        private readonly RedisClient $redis,
        private readonly int $defaultLimit = 60,
        private readonly int $defaultWindowSeconds = 60,
    ) {
    }

    /**
     * Verifica si el cliente puede realizar la acción.
     *
     * @param string $clientId       Identificador único (user ID o IP)
     * @param string $endpoint       Nombre del endpoint
     * @param int    $limit          Máximo de intentos en la ventana
     * @param int    $windowSeconds  Ventana de tiempo en segundos
     */
    public function allow(
        string $clientId,
        string $endpoint,
        int $limit = 0,
        int $windowSeconds = 0,
    ): bool {
        $limit = $limit > 0 ? $limit : $this->defaultLimit;
        $windowSeconds = $windowSeconds > 0 ? $windowSeconds : $this->defaultWindowSeconds;
        $key = $this->buildKey($clientId, $endpoint);

        try {
            // INCR es atómico: incrementa y retorna el nuevo valor
            $current = (int) $this->redis->incr($key);

            // En el primer intento (current === 1) fijamos el TTL
            if ($current === 1) {
                $this->redis->expire($key, $windowSeconds);
            }

            return $current <= $limit;
        } catch (ConnectionException $e) {
            // Redis no disponible: fail open con warning en error_log
            error_log('[RedisRateLimiter] WARNING: Redis no disponible — ' . $e->getMessage());

            return true;
        } catch (Throwable $e) {
            error_log('[RedisRateLimiter] ERROR inesperado: ' . $e->getMessage());

            return true;
        }
    }

    /**
     * Limpia el contador para el cliente (útil tras verificación de email, etc.).
     */
    public function reset(string $clientId, string $endpoint): void
    {
        try {
            $this->redis->del([$this->buildKey($clientId, $endpoint)]);
        } catch (Throwable $e) {
            error_log('[RedisRateLimiter] WARNING: No se pudo resetear — ' . $e->getMessage());
        }
    }

    /**
     * Devuelve el número de intentos actuales en la ventana.
     */
    public function getAttempts(string $clientId, string $endpoint, int $windowSeconds = 0): int
    {
        try {
            $value = $this->redis->get($this->buildKey($clientId, $endpoint));

            return $value === null ? 0 : (int) $value;
        } catch (Throwable $e) {
            error_log('[RedisRateLimiter] WARNING: No se pudo leer intentos — ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Devuelve los segundos que faltan para poder reintentar (TTL restante de la clave).
     */
    public function getRetryAfter(string $clientId, string $endpoint, int $windowSeconds = 0): int
    {
        try {
            $ttl = $this->redis->ttl($this->buildKey($clientId, $endpoint));

            // TTL < 0 significa que la clave no existe o no tiene expiración
            return max(0, (int) $ttl);
        } catch (Throwable $e) {
            error_log('[RedisRateLimiter] WARNING: No se pudo obtener TTL — ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Construye la clave Redis: "rate_limit:{clientId}:{endpoint}"
     */
    private function buildKey(string $clientId, string $endpoint): string
    {
        return self::KEY_PREFIX . $clientId . ':' . $endpoint;
    }
}
