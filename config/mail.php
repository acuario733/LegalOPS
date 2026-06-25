<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'transport' => Config::env('MAIL_TRANSPORT', 'local'),
    'spool_path' => Config::env('MAIL_SPOOL_PATH', 'storage/temp/mail'),
];
