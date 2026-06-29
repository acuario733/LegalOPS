<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\CasoComunicacionRepository;
use App\Repositories\CasoRepository;
use App\Repositories\NotificacionRepository;
use App\Repositories\UsuarioRepository;
use App\Services\CasoComunicacionService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CasoComunicacionServiceTest extends TestCase
{
    private PDO $pdo;
    private CasoComunicacionService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();
        $this->service = new CasoComunicacionService(
            new CasoComunicacionRepository($this->pdo),
            new CasoRepository($this->pdo),
            new UsuarioRepository($this->pdo),
            new NotificacionRepository($this->pdo),
            $this->database()
        );
    }

    public function test_create_comunicacion_with_valid_data(): void
    {
        [$firmaId, $casoId, $userId] = $this->tenant();

        $created = $this->service->create($casoId, $firmaId, $userId, [
            'tipo' => 'email',
            'direccion' => 'saliente',
            'asunto' => 'Seguimiento',
            'cuerpo' => 'Correo enviado al cliente.',
            'fecha_comunicacion' => '2026-06-28 10:30:00',
        ]);

        self::assertSame($firmaId, (int) $created['firma_id']);
        self::assertSame('email', $created['tipo']);
    }

    public function test_create_llamada_requires_duracion(): void
    {
        [$firmaId, $casoId, $userId] = $this->tenant();

        $this->expectException(HttpException::class);
        $this->service->create($casoId, $firmaId, $userId, [
            'tipo' => 'llamada',
            'direccion' => 'saliente',
            'fecha_comunicacion' => '2026-06-28 10:30:00',
        ]);
    }

    public function test_get_email_address_creates_if_not_exists(): void
    {
        [$firmaId, $casoId] = $this->tenant();

        $email = $this->service->getEmailAddress($casoId, $firmaId);

        self::assertStringStartsWith('caso-', $email);
        self::assertStringEndsWith('@inbound.legal.com', $email);
    }

    public function test_get_email_address_returns_same_on_second_call(): void
    {
        [$firmaId, $casoId] = $this->tenant();

        self::assertSame($this->service->getEmailAddress($casoId, $firmaId), $this->service->getEmailAddress($casoId, $firmaId));
    }

    public function test_process_inbound_email_creates_comunicacion(): void
    {
        [$firmaId, $casoId] = $this->tenant();
        $email = $this->service->getEmailAddress($casoId, $firmaId);

        $this->service->processInboundEmail([
            'to' => $email,
            'from' => 'cliente@example.com',
            'subject' => 'Respuesta',
            'body_text' => 'Texto recibido',
            'message_id' => 'msg-1',
            'attachments' => [],
        ]);

        $row = $this->pdo->query('SELECT * FROM caso_comunicaciones')->fetch();
        self::assertSame('email_bcc', $row['origen']);
        self::assertSame('entrante', $row['direccion']);
    }

    public function test_process_inbound_email_deduplicates_by_message_id(): void
    {
        [$firmaId, $casoId] = $this->tenant();
        $email = $this->service->getEmailAddress($casoId, $firmaId);
        $payload = ['to' => $email, 'from' => 'c@example.com', 'subject' => 'Uno', 'body_text' => 'Texto', 'message_id' => 'dup-1'];

        $this->service->processInboundEmail($payload);
        $this->service->processInboundEmail($payload);

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM caso_comunicaciones')->fetchColumn());
    }

    public function test_process_inbound_email_ignores_unknown_token(): void
    {
        $this->tenant();

        $this->service->processInboundEmail([
            'to' => 'caso-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@inbound.legal.com',
            'message_id' => 'unknown-1',
        ]);

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM caso_comunicaciones')->fetchColumn());
    }

    public function test_tenant_isolation_cannot_see_other_firma_comunicaciones(): void
    {
        [$firmaA, $casoA, $userA] = $this->tenant('A');
        [$firmaB, $casoB, $userB] = $this->tenant('B');
        $this->service->create($casoA, $firmaA, $userA, ['tipo' => 'mensaje', 'direccion' => 'interno', 'fecha_comunicacion' => '2026-06-28 10:00:00', 'cuerpo' => 'A']);
        $this->service->create($casoB, $firmaB, $userB, ['tipo' => 'mensaje', 'direccion' => 'interno', 'fecha_comunicacion' => '2026-06-28 10:00:00', 'cuerpo' => 'B']);

        $items = $this->service->list($casoA, $firmaA, []);

        self::assertCount(1, $items);
        self::assertSame($firmaA, (int) $items[0]['firma_id']);
    }

    /** @return array{int, int, int} */
    private function tenant(string $suffix = 'A'): array
    {
        $this->pdo->prepare('INSERT INTO firmas (nombre, slug, estado, timezone) VALUES (?, ?, ?, ?)')->execute(['Firma ' . $suffix, 'firma-' . strtolower($suffix), 'activa', 'UTC']);
        $firmaId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO usuarios (firma_id,nombre,email,email_normalizado,tipo,estado) VALUES (?, ?, ?, ?, ?, ?)')->execute([$firmaId, 'Abogado ' . $suffix, 'abogado' . $suffix . '@example.com', 'abogado' . $suffix . '@example.com', 'interno', 'activo']);
        $userId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO clientes (firma_id,nombre_razon_social,nombre_normalizado,estado) VALUES (?, ?, ?, ?)')->execute([$firmaId, 'Cliente ' . $suffix, 'cliente ' . strtolower($suffix), 'activo']);
        $clientId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO casos (firma_id,cliente_id,responsable_usuario_id,titulo,titulo_normalizado,estado,prioridad) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$firmaId, $clientId, $userId, 'Caso ' . $suffix, 'caso ' . strtolower($suffix), 'activo', 'media']);

        return [$firmaId, (int) $this->pdo->lastInsertId(), $userId];
    }

    private function database(): Database
    {
        $database = new Database(['driver' => 'mysql']);
        (new ReflectionProperty(Database::class, 'connection'))->setValue($database, $this->pdo);

        return $database;
    }

    private function schema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE firmas (id INTEGER PRIMARY KEY AUTOINCREMENT,nombre TEXT,slug TEXT,estado TEXT,timezone TEXT,deleted_at TEXT);
            CREATE TABLE usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre TEXT,email TEXT,email_normalizado TEXT,tipo TEXT,estado TEXT,deleted_at TEXT);
            CREATE TABLE clientes (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre_razon_social TEXT,nombre_normalizado TEXT,estado TEXT,deleted_at TEXT);
            CREATE TABLE casos (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,cliente_id INTEGER,responsable_usuario_id INTEGER,titulo TEXT,titulo_normalizado TEXT,estado TEXT,prioridad TEXT,deleted_at TEXT);
            CREATE TABLE caso_comunicaciones (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,caso_id INTEGER,tipo TEXT,direccion TEXT,asunto TEXT,cuerpo TEXT,participantes TEXT,fecha_comunicacion TEXT,duracion_minutos INTEGER,adjuntos TEXT,usuario_id INTEGER,origen TEXT,email_message_id TEXT UNIQUE,created_at TEXT,updated_at TEXT,deleted_at TEXT);
            CREATE TABLE caso_email_addresses (id INTEGER PRIMARY KEY AUTOINCREMENT,caso_id INTEGER,firma_id INTEGER,email_address TEXT UNIQUE,token TEXT UNIQUE,activo INTEGER,created_at TEXT);
            CREATE TABLE notificaciones (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,usuario_id INTEGER,titulo TEXT,mensaje TEXT,severidad TEXT,estado TEXT,origen_tipo TEXT,origen_id INTEGER,origen_url TEXT,dedupe_key TEXT UNIQUE,generated_at TEXT,created_at TEXT,updated_at TEXT);'
        );
    }
}
