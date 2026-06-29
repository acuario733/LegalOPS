<?php

declare(strict_types=1);

namespace Tests\Unit\Monitoring;

use App\Monitoring\MetricsCollector;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests de MetricsCollector.
 *
 * Verifica que cada check individual retorne la estructura correcta
 * y que el status global se calcule en base a los resultados individuales.
 */
class MetricsCollectorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
        parent::tearDown();
    }

    public function test_collect_returns_required_keys(): void
    {
        $collector = new MetricsCollector($this->pdo);
        $result    = $collector->collect();

        $this->assertArrayHasKey('status',      $result);
        $this->assertArrayHasKey('checks',      $result);
        $this->assertArrayHasKey('php_version', $result);
        $this->assertArrayHasKey('memory_mb',   $result);
        $this->assertArrayHasKey('timestamp',   $result);
    }

    public function test_status_is_ok_when_database_responds(): void
    {
        $collector = new MetricsCollector($this->pdo);
        $result    = $collector->collect();

        // SQLite :memory: siempre responde — database check debe ser ok
        $this->assertSame('ok', $result['checks']['database']['status']);
    }

    public function test_status_is_down_when_database_fails(): void
    {
        // PDO que lanza en cualquier query
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('query')->willThrowException(new \Exception('DB connection refused'));

        $collector = new MetricsCollector($pdoMock);
        $result    = $collector->collect();

        $this->assertSame('down', $result['checks']['database']['status']);
    }

    public function test_global_status_is_down_when_any_check_is_down(): void
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('query')->willThrowException(new \Exception('fail'));

        $collector = new MetricsCollector($pdoMock);
        $result    = $collector->collect();

        $this->assertSame('down', $result['status']);
    }

    public function test_global_status_is_ok_when_all_checks_pass(): void
    {
        $collector = new MetricsCollector($this->pdo);
        $result    = $collector->collect();

        // Con SQLite :memory: y sin log, todo debería ser ok
        $this->assertSame('ok', $result['status']);
    }

    public function test_checks_each_have_status_value_message(): void
    {
        $collector = new MetricsCollector($this->pdo);
        $result    = $collector->collect();

        foreach ($result['checks'] as $name => $check) {
            $this->assertArrayHasKey('status',  $check, "Check '$name' falta 'status'");
            $this->assertArrayHasKey('value',   $check, "Check '$name' falta 'value'");
            $this->assertArrayHasKey('message', $check, "Check '$name' falta 'message'");
            $this->assertContains(
                $check['status'],
                ['ok', 'degraded', 'down'],
                "Check '$name' tiene status inválido: {$check['status']}"
            );
        }
    }

    public function test_errors_24h_returns_zero_when_no_log_file(): void
    {
        $collector = new MetricsCollector($this->pdo, logPath: '/non/existent/path.log');
        $result    = $collector->collect();

        $this->assertSame(0, $result['checks']['errors_24h']['value']);
        $this->assertSame('ok', $result['checks']['errors_24h']['status']);
    }

    public function test_errors_24h_counts_recent_entries_from_log(): void
    {
        // Crear log temporal con 3 entradas recientes y 1 antigua
        $logFile = sys_get_temp_dir() . '/test_errors_' . uniqid() . '.log';

        $recent = json_encode(['timestamp' => gmdate('c'), 'message' => 'error reciente']) . PHP_EOL;
        $old    = json_encode(['timestamp' => '2020-01-01T00:00:00Z', 'message' => 'error viejo']) . PHP_EOL;

        file_put_contents($logFile, $recent . $recent . $recent . $old);

        try {
            $collector = new MetricsCollector($this->pdo, logPath: $logFile);
            $result    = $collector->collect();

            $this->assertSame(3, $result['checks']['errors_24h']['value']);
        } finally {
            @unlink($logFile);
        }
    }

    public function test_php_version_matches_current(): void
    {
        $collector = new MetricsCollector($this->pdo);
        $result    = $collector->collect();

        $this->assertSame(PHP_VERSION, $result['php_version']);
    }

    public function test_memory_mb_is_positive_number(): void
    {
        $collector = new MetricsCollector($this->pdo);
        $result    = $collector->collect();

        $this->assertIsFloat($result['memory_mb']);
        $this->assertGreaterThan(0.0, $result['memory_mb']);
    }
}
