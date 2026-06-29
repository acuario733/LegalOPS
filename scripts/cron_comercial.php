<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Services\CommercialBillingService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$sessionDir = $basePath . '/storage/temp/sessions';
if (!is_dir($sessionDir) && !mkdir($sessionDir, 0775, true) && !is_dir($sessionDir)) {
    fwrite(STDERR, "No fue posible preparar storage/temp/sessions.\n");
    exit(2);
}
ini_set('session.save_path', $sessionDir);

$logDir = $basePath . '/storage/logs';
if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, "No fue posible preparar storage/logs.\n");
    exit(2);
}

/** @param array<string, mixed> $context */
function cronComercialLog(string $logDir, string $event, array $context = []): void
{
    $record = ['ts' => gmdate('c'), 'process' => 'cron_comercial', 'event' => $event];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $record[$key] = is_string($value) ? mb_substr($value, 0, 180) : $value;
        }
    }
    file_put_contents($logDir . '/cron_comercial.log', json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$lockDir = $basePath . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "No fue posible preparar storage/locks.\n");
    cronComercialLog($logDir, 'failed_prepare_lock');
    exit(2);
}

$lock = fopen($lockDir . '/cron_comercial.lock', 'c');
if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "cron_comercial ya esta en ejecucion.\n");
    cronComercialLog($logDir, 'skipped_concurrent');
    exit(0);
}

$started = microtime(true);
try {
    cronComercialLog($logDir, 'started');
    $app = App::bootstrap($basePath);
    $request = new Request('CLI', '/scripts/cron_comercial.php', [], [], [], [], ['user-agent' => 'cron_comercial'], ['REMOTE_ADDR' => '127.0.0.1']);
    $result = $app->container()->get(CommercialBillingService::class)->suspendOverdue($request);
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    cronComercialLog($logDir, 'finished', [
        'status' => 'ok',
        'checked' => $result['checked'],
        'suspended' => $result['suspended'],
        'skipped' => $result['skipped'],
        'duration_ms' => $durationMs,
    ]);
    echo 'cron_comercial ok checked=' . $result['checked'] . ' suspended=' . $result['suspended'] . ' skipped=' . $result['skipped'] . ' duration_ms=' . $durationMs . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    $errorType = get_class($exception);
    cronComercialLog($logDir, 'failed', [
        'error_type' => $errorType,
        'error_ref' => hash('sha256', $errorType . '|' . $exception->getMessage()),
        'duration_ms' => $durationMs,
    ]);
    fwrite(STDERR, 'cron_comercial error; revise storage/logs/cron_comercial.log.' . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
