<?php

declare(strict_types=1);

/**
 * Configuración de Redis para LegalOPS Cloud V2.
 *
 * Usado por RedisRateLimiter (rate limiting en producción con múltiples workers).
 * Dejar REDIS_HOST vacío en desarrollo para usar el RateLimiter en memoria.
 */
return [
    'scheme' => (string) (getenv('REDIS_SCHEME') ?: 'tcp'),
    'host'   => (string) (getenv('REDIS_HOST')   ?: ''),
    'port'   => (int)    (getenv('REDIS_PORT')    ?: 6379),
];
