<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Services\AuditoriaService;
use App\Services\BillingService;
use App\Services\PdfService;
use App\Services\QueueService;
use App\Services\StorageService;
use PDO;
use PHPUnit\Framework\TestCase;

final class HonorarioServiceTest extends TestCase
{
    public function testAnularFacturaConPagosRetornaError(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            "CREATE TABLE firmas (id INTEGER PRIMARY KEY, nombre TEXT, logo_s3_key TEXT);
             CREATE TABLE clientes (id INTEGER PRIMARY KEY, firma_id INTEGER, nombre_razon_social TEXT, email TEXT);
             CREATE TABLE honorarios (
                id INTEGER PRIMARY KEY, firma_id INTEGER, cliente_id INTEGER, numero TEXT, concepto TEXT,
                monto NUMERIC, moneda TEXT, fecha_vencimiento TEXT, estado TEXT, pago_token TEXT,
                pago_token_expires_at TEXT, pdf_s3_key TEXT, deleted_at TEXT, anulado_motivo TEXT,
                anulado_at TEXT, anulado_por_usuario_id INTEGER, updated_at TEXT
             );
             CREATE TABLE pagos (
                id INTEGER PRIMARY KEY, firma_id INTEGER, honorario_id INTEGER, monto NUMERIC,
                estado TEXT, deleted_at TEXT
             );
             CREATE TABLE auditoria (
                id INTEGER PRIMARY KEY AUTOINCREMENT, firma_id INTEGER, usuario_id INTEGER, accion TEXT,
                modulo TEXT, entidad_tipo TEXT, entidad_id TEXT, severidad TEXT, correlation_id TEXT,
                ip_address TEXT, user_agent TEXT, metadata TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
             );
             CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, payload TEXT, attempts INTEGER,
                reserved_at INTEGER, available_at INTEGER, created_at INTEGER
             );
             INSERT INTO firmas VALUES (1,'Firma Uno',NULL);
             INSERT INTO clientes VALUES (10,1,'Cliente Uno','cliente@example.test');
             INSERT INTO honorarios
                (id,firma_id,cliente_id,numero,concepto,monto,moneda,fecha_vencimiento,estado,deleted_at)
                VALUES (20,1,10,'F-20','Servicios',1000,'COP','2026-07-30','emitido',NULL);
             INSERT INTO pagos VALUES (30,1,20,250,'registrado',NULL);"
        );
        $_SESSION = [];
        $session = new Session(['name' => 'billing_test_' . bin2hex(random_bytes(4)), 'secure' => false]);
        $auth = new Auth($session);
        $auth->login(['id' => 7, 'firma_id' => 1]);
        $audit = new AuditoriaService(new AuditoriaRepository($pdo), $auth);
        $service = new BillingService(
            $pdo,
            new PdfService(),
            new StorageService(),
            new QueueService($pdo),
            $audit
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No se puede anular una factura con pagos registrados.');

        $service->annul(1, 20, 'Error de facturacion', 7, new Request('POST', '/honorarios/20/anular'));
    }
}
