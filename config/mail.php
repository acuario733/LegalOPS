<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'transport' => Config::env('MAIL_TRANSPORT', 'local'),
    'spool_path' => Config::env('MAIL_SPOOL_PATH', 'storage/temp/mail'),
    'host' => Config::env('MAIL_HOST', '127.0.0.1'),
    'port' => (int) Config::env('MAIL_PORT', 1025),
    'username' => Config::env('MAIL_USERNAME', ''),
    'password' => Config::env('MAIL_PASSWORD', ''),
    'from_address' => Config::env('MAIL_FROM_ADDRESS', 'no-reply@legalops.local'),
    'from_name' => Config::env('MAIL_FROM_NAME', 'LegalOPS Cloud'),
];
