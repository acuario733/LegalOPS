<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Services\AuditoriaService;
use App\Services\GdprService;
use App\Services\StorageService;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Usa SQLite en memoria + dependencias reales para respetar el patrón del proyecto.
 * StorageService lanzará RuntimeException (S3 no configurado), que GdprService captura.
 */
#[Group('phase2c')]
class GdprServiceTest extends TestCase
{
    private PDO $pdo;
    private GdprService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->schema();

        $_SESSION = [];
        $session  = new Session(['name' => 'gdpr_test_' . bin2hex(random_bytes(4)), 'secure' => false]);
        $auth     = new Auth($session);
        $auth->login(['id' => 1, 'firma_id' => 1, 'permissions' => ['clientes.eliminar']]);

        $audit = new AuditoriaService(new AuditoriaRepository($this->pdo), $auth);

        $this->service = new GdprService($this->pdo, $audit, new StorageService());
    }

    // ── olvidar: caso feliz — anonimiza PII ──────────────────────────────────

    public function test_olvidar_anonimiza_pii_del_cliente(): void
    {
        $id = $this->insertCliente();
        $this->service->olvidar(1, $id, $this->req());

        $row = $this->pdo->query("SELECT * FROM clientes WHERE id = $id")->fetch();

        $this->assertSame('Datos anonimizados', $row['nombre_razon_social']);
        $this->assertNull($row['email']);
        $this->assertNull($row['telefono']);
        $this->assertNull($row['numero_documento']);
        $this->assertNull($row['direccion']);
        $this->assertNotNull($row['olvidado_at']);
    }

    // ── olvidar: segunda llamada → 409 ───────────────────────────────────────

    public function test_olvidar_lanza_409_si_ya_fue_anonimizado(): void
    {
        $id = $this->insertCliente(olvidadoAt: '2026-01-01 00:00:00');

        try {
            $this->service->olvidar(1, $id, $this->req());
            $this->fail('Se esperaba HttpException 409.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->status());
        }
    }

    // ── olvidar: cliente inexistente → 404 ───────────────────────────────────

    public function test_olvidar_lanza_404_si_cliente_no_existe(): void
    {
        try {
            $this->service->olvidar(1, 9999, $this->req());
            $this->fail('Se esperaba HttpException 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status());
        }
    }

    // ── olvidar: tenant isolation — otra firma no puede olvidar ──────────────

    public function test_olvidar_lanza_404_si_cliente_es_de_otra_firma(): void
    {
        $id = $this->insertCliente(firmaId: 2);

        try {
            $this->service->olvidar(1, $id, $this->req());
            $this->fail('Se esperaba HttpException 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status());
        }
    }

    // ── exportarDatos: sin S3 retorna url=null y no lanza excepción ──────────

    public function test_exportar_datos_sin_s3_retorna_url_null(): void
    {
        $id     = $this->insertCliente();
        $result = $this->service->exportarDatos(1, $id, $this->req());

        // S3 no configurado → GdprService captura RuntimeException y retorna null
        $this->assertNull($result['url']);
        $this->assertSame(86400, $result['expires_in']);
    }

    // ── exportarDatos: registro queda en exportaciones_datos ─────────────────

    public function test_exportar_datos_registra_en_exportaciones_datos(): void
    {
        $id = $this->insertCliente();
        $this->service->exportarDatos(1, $id, $this->req());

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM exportaciones_datos')->fetchColumn();
        $this->assertSame(1, $count);
    }

    // ── exportarDatos: cliente inexistente → 404 ─────────────────────────────

    public function test_exportar_datos_lanza_404_si_cliente_no_existe(): void
    {
        try {
            $this->service->exportarDatos(1, 9999, $this->req());
            $this->fail('Se esperaba HttpException 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status());
        }
    }

    // ── exportarDatos: tenant isolation ──────────────────────────────────────

    public function test_exportar_datos_lanza_404_si_cliente_es_de_otra_firma(): void
    {
        $id = $this->insertCliente(firmaId: 2);

        try {
            $this->service->exportarDatos(1, $id, $this->req());
            $this->fail('Se esperaba HttpException 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status());
        }
    }

    // ── exportarDatos: el cliente olvidado puede exportarse (datos ya anon.) ──

    public function test_exportar_datos_funciona_para_cliente_ya_anonimizado(): void
    {
        $id = $this->insertCliente(olvidadoAt: '2026-01-01 00:00:00');

        $result = $this->service->exportarDatos(1, $id, $this->req());

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('expires_in', $result);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function insertCliente(int $firmaId = 1, ?string $olvidadoAt = null): int
    {
        $this->pdo->prepare(
            'INSERT INTO clientes (firma_id, nombre_razon_social, nombre_normalizado,
             email, telefono, numero_documento, direccion, olvidado_at)
             VALUES (:fid, :nom, :norm, :email, :tel, :doc, :dir, :olv)'
        )->execute([
            'fid'  => $firmaId,
            'nom'  => 'Juan Pérez',
            'norm' => 'juan perez',
            'email' => 'juan@ejemplo.com',
            'tel'  => '555-1234',
            'doc'  => '12345678',
            'dir'  => 'Calle 1 # 2-3',
            'olv'  => $olvidadoAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function req(): Request
    {
        return new Request('POST', '/clientes/1/olvidar');
    }

    private function schema(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE clientes (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id             INTEGER NOT NULL,
                tipo_persona         TEXT NOT NULL DEFAULT 'natural',
                nombre_razon_social  TEXT NOT NULL,
                nombre_normalizado   TEXT NOT NULL,
                tipo_documento       TEXT,
                numero_documento     TEXT,
                documento_normalizado TEXT,
                documento_hash       TEXT,
                email                TEXT,
                telefono             TEXT,
                direccion            TEXT,
                estado               TEXT NOT NULL DEFAULT 'activo',
                origen               TEXT,
                observaciones        TEXT,
                tratamiento_datos_autorizado INTEGER NOT NULL DEFAULT 0,
                autorizacion_tratamiento_at  TEXT,
                created_at           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at           TEXT,
                olvidado_at          TEXT
            );
            CREATE TABLE exportaciones_datos (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id   INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                s3_key     TEXT,
                expires_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE casos (
                id INTEGER PRIMARY KEY, firma_id INTEGER, numero TEXT, titulo TEXT,
                estado TEXT, tipo_proceso TEXT, created_at TEXT, deleted_at TEXT
            );
            CREATE TABLE caso_partes (
                id INTEGER PRIMARY KEY, caso_id INTEGER, cliente_id INTEGER
            );
            CREATE TABLE honorarios (
                id INTEGER PRIMARY KEY, firma_id INTEGER, cliente_id INTEGER,
                numero_factura TEXT, estado TEXT, monto REAL, moneda TEXT,
                created_at TEXT, vencimiento_at TEXT, deleted_at TEXT
            );
            CREATE TABLE pagos (
                id INTEGER PRIMARY KEY, honorario_id INTEGER, monto REAL,
                metodo TEXT, estado TEXT, created_at TEXT, deleted_at TEXT
            );
            CREATE TABLE auditoria (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id       INTEGER,
                usuario_id     INTEGER,
                accion         TEXT NOT NULL,
                modulo         TEXT NOT NULL,
                entidad_tipo   TEXT,
                entidad_id     TEXT,
                severidad      TEXT NOT NULL,
                correlation_id TEXT,
                ip_address     TEXT,
                user_agent     TEXT,
                metadata       TEXT,
                created_at     TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        SQL);
    }
}
