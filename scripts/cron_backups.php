<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;

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
function cronBackupsLog(string $logDir, string $event, array $context = []): void
{
    $record = ['ts' => gmdate('c'), 'process' => 'cron_backups', 'event' => $event];
    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $record[$key] = is_string($value) ? mb_substr($value, 0, 180) : $value;
        }
    }
    file_put_contents($logDir . '/cron_backups.log', json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$lockDir = $basePath . '/storage/locks';
if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "No fue posible preparar storage/locks.\n");
    cronBackupsLog($logDir, 'failed_prepare_lock');
    exit(2);
}

$lock = fopen($lockDir . '/cron_backups.lock', 'c');
if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "cron_backups ya esta en ejecucion.\n");
    cronBackupsLog($logDir, 'skipped_concurrent');
    exit(0);
}

$started = microtime(true);
try {
    cronBackupsLog($logDir, 'started');
    App::bootstrap($basePath);
    $config = (array) Config::get('database', []);
    $backupDir = (string) Config::get('app.backup_path', $basePath . '/storage/backups');
    if (!is_dir($backupDir) && !mkdir($backupDir, 0770, true) && !is_dir($backupDir)) {
        throw new RuntimeException('No fue posible preparar storage/backups.');
    }

    $database = (string) ($config['database'] ?? '');
    if ($database === '') {
        throw new RuntimeException('DB_DATABASE no esta configurado.');
    }

    $target = $backupDir . DIRECTORY_SEPARATOR . 'legalops_' . date('Ymd_His') . '.sql';
    $command = buildDumpCommand(locateMysqldump(), $config, $target, $database);
    $password = (string) ($config['password'] ?? '');
    $previousMysqlPwd = getenv('MYSQL_PWD');
    if ($password !== '') {
        putenv('MYSQL_PWD=' . $password);
    }
    exec($command . ' 2>&1', $output, $code);
    if ($password !== '') {
        putenv($previousMysqlPwd === false ? 'MYSQL_PWD' : 'MYSQL_PWD=' . $previousMysqlPwd);
    }
    if ($code !== 0 || !is_file($target) || filesize($target) === 0) {
        @unlink($target);
        throw new RuntimeException('mysqldump fallo.');
    }

    $removed = cleanupOldBackups($backupDir, (int) Config::get('app.backup_retention_days', 14));
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    cronBackupsLog($logDir, 'finished', [
        'status' => 'ok',
        'file' => basename($target),
        'bytes' => filesize($target),
        'removed' => $removed,
        'duration_ms' => $durationMs,
    ]);
    echo 'cron_backups ok archivo=' . basename($target) . ' bytes=' . filesize($target) . ' removed=' . $removed . ' duration_ms=' . $durationMs . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    $durationMs = (int) round((microtime(true) - $started) * 1000);
    $errorType = get_class($exception);
    cronBackupsLog($logDir, 'failed', [
        'error_type' => $errorType,
        'error_ref' => hash('sha256', $errorType . '|' . $exception->getMessage()),
        'duration_ms' => $durationMs,
    ]);
    fwrite(STDERR, 'cron_backups error; revise storage/logs/cron_backups.log.' . PHP_EOL);
    exit(1);
} finally {
    if (isset($password) && $password !== '') {
        putenv(isset($previousMysqlPwd) && $previousMysqlPwd !== false ? 'MYSQL_PWD=' . $previousMysqlPwd : 'MYSQL_PWD');
    }
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function locateMysqldump(): string
{
    $configured = Config::env('MYSQLDUMP_BIN', '');
    if (is_string($configured) && $configured !== '' && is_file($configured)) {
        return $configured;
    }

    $xampp = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (is_file($xampp)) {
        return $xampp;
    }

    return 'mysqldump';
}

/** @param array<string, mixed> $config */
function buildDumpCommand(string $binary, array $config, string $target, string $database): string
{
    $args = [
        escapeshellarg($binary),
        '--host=' . escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
        '--port=' . escapeshellarg((string) ($config['port'] ?? '3306')),
        '--user=' . escapeshellarg((string) ($config['username'] ?? '')),
        '--default-character-set=' . escapeshellarg((string) ($config['charset'] ?? 'utf8mb4')),
        '--single-transaction',
        '--routines',
        '--triggers',
        '--events',
        '--result-file=' . escapeshellarg($target),
    ];
    $args[] = escapeshellarg($database);

    return implode(' ', $args);
}

function cleanupOldBackups(string $backupDir, int $retentionDays): int
{
    $cutoff = time() - max(1, $retentionDays) * 86400;
    $removed = 0;
    foreach (glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
        if (is_file($file) && filemtime($file) !== false && filemtime($file) < $cutoff && @unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}
