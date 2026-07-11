<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\ReporteRepository;
use App\Services\ReporteService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReporteAgingServiceTest extends TestCase
{
    public function testGroupsOnlyOverduePendingInvoicesAndTotalsBuckets(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE clientes (id INTEGER,firma_id INTEGER,nombre_razon_social TEXT)');
        $pdo->exec('CREATE TABLE honorarios (id INTEGER,firma_id INTEGER,cliente_id INTEGER,numero TEXT,monto REAL,moneda TEXT,fecha_vencimiento TEXT,estado TEXT,deleted_at TEXT)');
        $pdo->exec("INSERT INTO clientes VALUES (1,1,'Cliente Uno'),(2,2,'Otro tenant')");
        $pdo->exec("INSERT INTO honorarios VALUES
            (1,1,1,'FAC-1',100,'COP','2026-06-19','pendiente',NULL),
            (2,1,1,'FAC-2',200,'COP','2026-04-20','pendiente',NULL),
            (3,1,1,'FAC-3',300,'COP','2026-01-01','pagado',NULL),
            (4,2,2,'FAC-X',999,'COP','2026-01-01','pendiente',NULL)");
        $service = new ReporteService(new ReporteRepository($pdo));

        $aging = $service->getAging(1, '2026-06-29');

        self::assertCount(1, $aging['0-30']['items']);
        self::assertSame(100.0, $aging['0-30']['total']);
        self::assertCount(1, $aging['61-90']['items']);
        self::assertSame(200.0, $aging['61-90']['total']);
        self::assertSame(0.0, $aging['mas90']['total']);
    }
}
