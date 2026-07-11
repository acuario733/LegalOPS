<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'enabled'           => (bool) Config::env('MFA_ENABLED', false),
    'issuer'            => (string) Config::env('MFA_ISSUER', 'LegalOPS Cloud'),
    'digits'            => (int)    Config::env('MFA_DIGITS', 6),
    'window'            => (int)    Config::env('MFA_WINDOW', 1),   // ventanas ±1 de 30s
    'attempts_max'      => (int)    Config::env('MFA_ATTEMPTS_MAX', 5),
    'attempts_ttl'      => (int)    Config::env('MFA_ATTEMPTS_TTL', 900), // segundos (15 min)
    'recovery_length'   => (int)    Config::env('MFA_RECOVERY_LENGTH', 8),
    'recovery_count'    => (int)    Config::env('MFA_RECOVERY_COUNT', 8),
    'recovery_queue'    => (string) Config::env('MFA_RECOVERY_QUEUE', 'mfa.recovery_otp.send'),
    'encryption_key'    => (string) Config::env('MFA_ENCRYPTION_KEY', ''), // clave dedicada; cae en APP_KEY si vacia
    'require_for_roles' => array_filter(explode(',', (string) Config::env('MFA_REQUIRE_FOR_ROLES', 'superadmin,admin'))),
];
