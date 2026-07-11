<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RetainerService;
use PDO;
use PHPUnit\Framework\TestCase;

final class RetainerServiceTest extends TestCase
{
    public function testCreateSchedulesAndScopesActiveRetainersByTenant(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE honorario_retainers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,cliente_id INTEGER,caso_id INTEGER,
                monto REAL,moneda TEXT,dia_cobro INTEGER,activo INTEGER,proximo_cobro_at TEXT,
                ultimo_cobro_at TEXT,created_at TEXT,updated_at TEXT,deleted_at TEXT
            )'
        );
        $service = new RetainerService($pdo);

        $id = $service->create(1, ['cliente_id' => 8, 'monto' => 250000, 'moneda' => 'cop', 'dia_cobro' => 5]);
        $pdo->exec("INSERT INTO honorario_retainers (firma_id,cliente_id,monto,moneda,dia_cobro,activo,proximo_cobro_at,created_at,updated_at) VALUES (2,9,1,'COP',1,1,'2026-07-01',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");

        self::assertGreaterThan(0, $id);
        self::assertCount(1, $service->active(1));
        self::assertSame('COP', $service->active(1)[0]['moneda']);
    }
}
