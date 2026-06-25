<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Services\NotificacionService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$logDir = $basePath . '/storage/logs';
if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, "No fue posible preparar storage/logs.\n");
    exit(2);
}

/**
 * @param array<string, mixed> $context
 */
function cronAlertasLog(string $logDir, string $event, array $context = []): void
{
    $record = [
        'ts' => gmdate('c'),
        'process' => 'cron_alertas',
        'event' => $event,
    ];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $record[$key] = is_string($value) ? mb_substr($value, 0, 180) : $value;
        }
    }

    file_put_contents($logDir . '/cron_alertas.log', json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$lockDir = $basePath . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "No fue posible preparar storage/locks.\n");
    cronAlertasLog($logDir, 'failed_prepare_lock');
    exit(2);
}

$lock = fopen($lockDir . '/cron_alertas.lock', 'c');
if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "cron_alertas ya esta en ejecucion.\n");
    cronAlertasLog($logDir, 'skipped_concurrent');
    exit(0);
}

$started = microtime(true);
try {
    cronAlertasLog($logDir, 'started');
    $app = App::bootstrap($basePath);
    $request = new Request('CLI', '/scripts/cron_alertas.php', [], [], [], [], ['user-agent' => 'cron_alertas'], ['REMOTE_ADDR' => '127.0.0.1']);
    $result = $app->container()->get(NotificacionService::class)->generateAll($request);
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    $status = $result['errors'] > 0 ? 'partial_error' : 'ok';
    cronAlertasLog($logDir, 'finished', [
        'status' => $status,
        'created' => $result['created'],
        'checked' => $result['checked'],
        'errors' => $result['errors'],
        'duration_ms' => $durationMs,
    ]);
    echo 'cron_alertas ' . $status . ' created=' . $result['created'] . ' checked=' . $result['checked'] . ' errors=' . $result['errors'] . ' duration_ms=' . $durationMs . PHP_EOL;
    exit($result['errors'] > 0 ? 1 : 0);
} catch (Throwable $exception) {
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    $errorType = get_class($exception);
    cronAlertasLog($logDir, 'failed', [
        'error_type' => $errorType,
        'error_ref' => hash('sha256', $errorType . '|' . $exception->getMessage()),
        'duration_ms' => $durationMs,
    ]);
    fwrite(STDERR, 'cron_alertas error; revise storage/logs/cron_alertas.log.' . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
