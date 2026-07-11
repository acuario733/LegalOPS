<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\NotificacionRepository;
use App\Services\AuditoriaService;
use App\Services\NotificacionService;
use App\Services\PdfService;
use App\Services\StorageService;
use App\Services\TrustService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TrustServiceTest extends TestCase
{
    private PDO $pdo;
    private TrustService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->schema();

        $_SESSION = [];
        $session = new Session(['name' => 'legalops_test_' . bin2hex(random_bytes(4)), 'secure' => false]);
        $auth = new Auth($session);
        $auth->login(['id' => 7, 'firma_id' => 1, 'permissions' => ['trust.*']]);

        $audit = new AuditoriaService(new AuditoriaRepository($this->pdo), $auth);
        $notifications = new NotificacionService(new NotificacionRepository($this->pdo), $audit, $auth);

        $this->service = new TrustService(
            $this->pdo,
            new ClienteRepository($this->pdo),
            new CasoRepository($this->pdo),
            $audit,
            $auth,
            $notifications,
            new PdfService(),
            new StorageService()
        );
    }

    public function testDepositarIncrementsBalanceAndCreatesTransaction(): void
    {
        // Arrange / Act
        $transaction = $this->service->depositar(1, 10, null, '150.00', 'Deposito inicial', 'REF-1');

        // Assert
        self::assertSame('deposito', $transaction['tipo']);
        self::assertSame(150.0, (float) $this->pdo->query('SELECT saldo FROM trust_accounts WHERE id=1')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM trust_transactions')->fetchColumn());
    }

    public function testRetirarMayorAlSaldoLanzaHttpException(): void
    {
        // Arrange
        $this->service->depositar(1, 10, null, '100.00', 'Deposito inicial', null);

        // Assert
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Saldo insuficiente');

        // Act
        $this->service->retirar(1, 1, '150.00', 'Retiro no permitido', null, 10);
    }

    private function schema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                nombre_razon_social TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE casos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE trust_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                caso_id INTEGER NULL,
                moneda TEXT NOT NULL DEFAULT 'COP',
                saldo NUMERIC NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE trust_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                trust_account_id INTEGER NOT NULL,
                tipo TEXT NOT NULL,
                monto NUMERIC NOT NULL,
                descripcion TEXT NOT NULL,
                referencia TEXT NULL,
                usuario_id INTEGER NULL,
                fecha TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE auditoria (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NULL,
                usuario_id INTEGER NULL,
                accion TEXT NOT NULL,
                modulo TEXT NOT NULL,
                entidad_tipo TEXT NULL,
                entidad_id TEXT NULL,
                severidad TEXT NOT NULL,
                correlation_id TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                metadata TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE notificaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                mensaje TEXT NOT NULL,
                severidad TEXT NOT NULL,
                estado TEXT NOT NULL,
                origen_tipo TEXT NOT NULL,
                origen_id INTEGER NOT NULL,
                origen_url TEXT NOT NULL,
                dedupe_key TEXT NOT NULL,
                generated_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                tipo TEXT NOT NULL DEFAULT 'interno',
                estado TEXT NOT NULL DEFAULT 'activo',
                deleted_at TEXT NULL
            );
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                codigo TEXT NOT NULL,
                estado TEXT NOT NULL DEFAULT 'activo',
                deleted_at TEXT NULL
            );
            CREATE TABLE usuario_roles (
                usuario_id INTEGER NOT NULL,
                firma_id INTEGER NOT NULL,
                rol_id INTEGER NOT NULL
            );"
        );
        $this->pdo->exec("INSERT INTO clientes (id,firma_id,nombre_razon_social) VALUES (10,1,'Cliente Trust')");
    }
}
