<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\ReporteRepository;
use App\Services\ReporteService;
use PDO;
use PHPUnit\Framework\TestCase;

final class CrmReportServiceTest extends TestCase
{
    public function testCalculatesConversionSourcesAndStageDurationsPerTenant(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE prospectos (id INTEGER,firma_id INTEGER,estado TEXT,fuente TEXT,created_at TEXT,estado_updated_at TEXT,deleted_at TEXT)');
        $pdo->exec('CREATE TABLE prospecto_historial_estados (id INTEGER,firma_id INTEGER,prospecto_id INTEGER,estado_anterior TEXT,estado_nuevo TEXT,created_at TEXT)');
        $pdo->exec("INSERT INTO prospectos VALUES
            (1,1,'ganado','web','2026-06-01 00:00:00','2026-06-03 00:00:00',NULL),
            (2,1,'nuevo','referido','2026-06-02 00:00:00','2026-06-02 00:00:00',NULL),
            (3,2,'ganado','web','2026-06-01 00:00:00','2026-06-01 00:00:00',NULL)");
        $pdo->exec("INSERT INTO prospecto_historial_estados VALUES
            (1,1,1,'nuevo','contactado','2026-06-02 00:00:00'),
            (2,1,1,'contactado','ganado','2026-06-03 00:00:00')");
        $service = new ReporteService(new ReporteRepository($pdo));

        $report = $service->getCrm(1, '2026-06-01', '2026-06-30');

        self::assertSame(50.0, $report['tasa_conversion']);
        self::assertSame(['referido' => 1, 'web' => 1], $report['leads_por_fuente']);
        self::assertSame(1.0, $report['tiempo_promedio_por_etapa']['nuevo']);
        self::assertSame(1.0, $report['tiempo_promedio_por_etapa']['contactado']);
    }
}
