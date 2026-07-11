<?php

declare(strict_types=1);

namespace App\Monitoring;

use PDO;

/**
 * MetricsCollector — recopila métricas del sistema para el endpoint /health.
 *
 * Métricas recogidas:
 * - Estado de la base de datos (ping SELECT 1)
 * - Uso de disco (storage/)
 * - Tamaño del log de errores
 * - Número de errores en las últimas 24h (desde el log JSON)
 * - Versión de PHP
 * - Memoria PHP en uso
 *
 * NO usa cachés externas — cada llamada a collect() es un snapshot en tiempo real.
 * Mantener los checks individuales bajo 100ms cada uno.
 */
final class MetricsCollector
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $logPath = '',
        private readonly string $storagePath = '',
    ) {
    }

    /**
     * Retorna el status completo del sistema.
     *
     * @return array{
     *   status: 'ok'|'degraded'|'down',
     *   checks: array<string, array{status: string, value: mixed, message: string}>,
     *   php_version: string,
     *   memory_mb: float,
     *   timestamp: string,
     * }
     */
    public function collect(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'disk'     => $this->checkDisk(),
            'log_size' => $this->checkLogSize(),
            'errors_24h' => $this->countRecentErrors(),
        ];

        // Determinar status global
        $anyDown     = false;
        $anyDegraded = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'down') {
                $anyDown = true;
            } elseif ($check['status'] === 'degraded') {
                $anyDegraded = true;
            }
        }

        return [
            'status'      => $anyDown ? 'down' : ($anyDegraded ? 'degraded' : 'ok'),
            'checks'      => $checks,
            'php_version' => PHP_VERSION,
            'memory_mb'   => round(memory_get_usage(true) / 1024 / 1024, 2),
            'timestamp'   => gmdate('c'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Checks individuales
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{status: string, value: mixed, message: string} */
    private function checkDatabase(): array
    {
        try {
            $start = hrtime(true);
            $this->pdo->query('SELECT 1');
            $ms = (hrtime(true) - $start) / 1_000_000;

            $status  = $ms > 500 ? 'degraded' : 'ok';
            $message = $status === 'ok' ? 'OK' : 'Respuesta lenta (' . round($ms) . 'ms)';

            return ['status' => $status, 'value' => round($ms, 2), 'message' => $message];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'value' => null, 'message' => 'Conexión fallida'];
        }
    }

    /** @return array{status: string, value: mixed, message: string} */
    private function checkDisk(): array
    {
        $path = $this->storagePath !== '' ? $this->storagePath : sys_get_temp_dir();

        $free  = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total === 0.0) {
            return ['status' => 'degraded', 'value' => null, 'message' => 'No se pudo leer el disco'];
        }

        $usedPct = round((1 - $free / $total) * 100, 1);
        $freeMb  = round($free / 1024 / 1024);

        $status = match (true) {
            $usedPct >= 95 => 'down',
            $usedPct >= 85 => 'degraded',
            default        => 'ok',
        };

        return [
            'status'  => $status,
            'value'   => ['used_pct' => $usedPct, 'free_mb' => $freeMb],
            'message' => "{$usedPct}% usado ({$freeMb} MB libres)",
        ];
    }

    /** @return array{status: string, value: mixed, message: string} */
    private function checkLogSize(): array
    {
        if ($this->logPath === '' || !file_exists($this->logPath)) {
            return ['status' => 'ok', 'value' => 0, 'message' => 'Sin log'];
        }

        $bytes = filesize($this->logPath);
        if ($bytes === false) {
            return ['status' => 'ok', 'value' => 0, 'message' => 'No se pudo leer'];
        }

        $mb     = round($bytes / 1024 / 1024, 2);
        $status = match (true) {
            $mb >= 100 => 'degraded',
            default    => 'ok',
        };

        return ['status' => $status, 'value' => $mb, 'message' => "{$mb} MB"];
    }

    /** @return array{status: string, value: mixed, message: string} */
    private function countRecentErrors(): array
    {
        if ($this->logPath === '' || !file_exists($this->logPath)) {
            return ['status' => 'ok', 'value' => 0, 'message' => 'Sin errores recientes'];
        }

        $count     = 0;
        $threshold = time() - 86400; // últimas 24h

        try {
            $handle = @fopen($this->logPath, 'r');
            if ($handle === false) {
                return ['status' => 'ok', 'value' => 0, 'message' => 'No se pudo leer el log'];
            }

            while (($line = fgets($handle)) !== false) {
                $record = json_decode(trim($line), true);
                if (is_array($record) && (isset($record['timestamp']) || isset($record['ts']))) {
                    $ts = strtotime((string) ($record['ts'] ?? $record['timestamp']));
                    if ($ts !== false && $ts >= $threshold) {
                        $count++;
                    }
                }
            }
            fclose($handle);
        } catch (\Throwable) {
            // Si falla, no bloqueamos el health check
        }

        $status = match (true) {
            $count >= 100 => 'down',
            $count >= 20  => 'degraded',
            default       => 'ok',
        };

        return [
            'status'  => $status,
            'value'   => $count,
            'message' => "{$count} errores en las últimas 24h",
        ];
    }
}
