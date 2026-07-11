<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Monitoring\MetricsCollector;
use App\Services\StorageService;
use PDO;
use Predis\Client as RedisClient;
use RuntimeException;
use Throwable;

final class HealthController extends Controller
{
    public function html(Request $request): Response
    {
        return $this->view('system/health', [
            'title' => 'Estado del núcleo',
            'environment' => (string) Config::get('app.environment', 'production'),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function api(Request $request): Response
    {
        $checks = [
            'storage_writable' => is_writable(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage'),
            'database' => $this->databaseIsAvailable(),
        ];
        $healthy = !in_array(false, $checks, true);

        return $this->json([
            'service' => (string) Config::get('app.name', 'LegalOPS Cloud'),
            'status' => $healthy ? 'ok' : 'degraded',
            'environment' => (string) Config::get('app.environment', 'production'),
            'checks' => $checks,
        ], $healthy ? 'Servicio disponible.' : 'Servicio con verificacion degradada.', $healthy ? 200 : 503);
    }

    public function post(Request $request): Response
    {
        return $this->json(['status' => 'ok'], 'Token CSRF validado correctamente.');
    }

    public function ready(Request $request): Response
    {
        $checks = [
            'database' => $this->databaseIsAvailable(),
            'redis' => $this->redisIsAvailable(),
            's3' => $this->s3IsAvailable(),
            'queue' => $this->queueMetrics(),
        ];
        $healthy = $checks['database'] === true;

        return $this->json(
            $checks,
            $healthy ? 'Servicio listo.' : 'Servicio no listo.',
            $healthy ? 200 : 503,
            [],
            $healthy
        );
    }

    public function queue(Request $request): Response
    {
        return $this->json($this->queueMetrics(), 'Estado de la cola.');
    }

    /**
     * GET /api/health/metrics — Estado detallado del sistema (solo acceso interno).
     *
     * Devuelve: estado de BD, disco, errores recientes, memoria PHP.
     * HTTP 200 si ok, 503 si degraded/down.
     */
    public function metrics(Request $request): Response
    {
        $basePath   = dirname(__DIR__, 2);
        $logPath    = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'errors.log';
        $storage    = $basePath . DIRECTORY_SEPARATOR . 'storage';

        $collector = new MetricsCollector(
            pdo:         $this->container->get(\PDO::class),
            logPath:     $logPath,
            storagePath: $storage,
        );

        $data       = $collector->collect();
        $httpStatus = $data['status'] === 'ok' ? 200 : 503;

        return $this->json($data, "Sistema: {$data['status']}", $httpStatus);
    }

    public function controlledError(Request $request): Response
    {
        throw new RuntimeException('Fallo controlado de verificación; token=valor-no-real');
    }

    private function databaseIsAvailable(): bool
    {
        try {
            $this->container->get(Database::class)->connection()->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisIsAvailable(): bool|string
    {
        if ((string) Config::get('redis.host', '') === '') {
            return 'not_configured';
        }

        try {
            return strtoupper((string) $this->container->get(RedisClient::class)->ping()) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    private function s3IsAvailable(): bool|string
    {
        if (trim((string) Config::env('S3_BUCKET', '')) === '') {
            return 'not_configured';
        }

        try {
            return $this->container->get(StorageService::class)->healthCheck();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{pendientes: int, procesando: int, fallidos: int} */
    private function queueMetrics(): array
    {
        try {
            $pdo = $this->container->get(PDO::class);

            return [
                'pendientes' => (int) $pdo->query('SELECT COUNT(*) FROM jobs WHERE reserved_at IS NULL')->fetchColumn(),
                'procesando' => (int) $pdo->query('SELECT COUNT(*) FROM jobs WHERE reserved_at IS NOT NULL')->fetchColumn(),
                'fallidos' => (int) $pdo->query('SELECT COUNT(*) FROM failed_jobs')->fetchColumn(),
            ];
        } catch (Throwable) {
            return ['pendientes' => -1, 'procesando' => -1, 'fallidos' => -1];
        }
    }
}
