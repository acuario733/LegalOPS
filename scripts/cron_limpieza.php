<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Repositories\UserSessionRepository;
use App\Services\ExportacionService;
use App\Services\ImportacionService;

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

/** @param array<string, mixed> $context */
function cronLimpiezaLog(string $logDir, string $event, array $context = []): void
{
    $record = ['ts' => gmdate('c'), 'process' => 'cron_limpieza', 'event' => $event];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $record[$key] = is_string($value) ? mb_substr($value, 0, 180) : $value;
        }
    }
    file_put_contents($logDir . '/cron_limpieza.log', json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$lockDir = $basePath . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "No fue posible preparar storage/locks.\n");
    cronLimpiezaLog($logDir, 'failed_prepare_lock');
    exit(2);
}

$lock = fopen($lockDir . '/cron_limpieza.lock', 'c');
if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "cron_limpieza ya esta en ejecucion.\n");
    cronLimpiezaLog($logDir, 'skipped_concurrent');
    exit(0);
}

$started = microtime(true);
try {
    cronLimpiezaLog($logDir, 'started');
    $app = App::bootstrap($basePath);
    $sessions = $app->container()->get(UserSessionRepository::class)->cleanupExpired();
    $exports = $app->container()->get(ExportacionService::class)->cleanupExpired();
    $imports = $app->container()->get(ImportacionService::class)->cleanupExpired();
    $logs = cleanupOldLogs($logDir, (int) Config::get('app.log_retention_days', 30));
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    cronLimpiezaLog($logDir, 'finished', [
        'status' => 'ok',
        'sessions' => $sessions,
        'exports' => $exports,
        'imports' => $imports,
        'logs' => $logs,
        'duration_ms' => $durationMs,
    ]);
    echo 'cron_limpieza ok sesiones=' . $sessions . ' exportaciones=' . $exports . ' importaciones=' . $imports . ' logs=' . $logs . ' duration_ms=' . $durationMs . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    $errorType = get_class($exception);
    cronLimpiezaLog($logDir, 'failed', [
        'error_type' => $errorType,
        'error_ref' => hash('sha256', $errorType . '|' . $exception->getMessage()),
        'duration_ms' => $durationMs,
    ]);
    fwrite(STDERR, 'cron_limpieza error; revise storage/logs/cron_limpieza.log.' . PHP_EOL);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function cleanupOldLogs(string $logDir, int $retentionDays): int
{
    $cutoff = time() - max(1, $retentionDays) * 86400;
    $removed = 0;
    foreach (glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $file) {
        if (is_file($file) && filemtime($file) !== false && filemtime($file) < $cutoff && @unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}
