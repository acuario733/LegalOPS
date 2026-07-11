<?php

declare(strict_types=1);

use App\Core\App;
use App\Queue\JobRunner;

require __DIR__ . '/../vendor/autoload.php';

$app = App::bootstrap(dirname(__DIR__));
$queue = $argv[1] ?? 'default';
$once = in_array('--once', $argv, true);

$app->container()->get(JobRunner::class)->work($queue, $once);
