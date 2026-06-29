<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\DocumentTemplateRepository;
use App\Repositories\DocumentoRepository;
use App\Repositories\DocumentoVersionRepository;
use App\Repositories\FirmaRepository;
use App\Repositories\GastoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditoriaService;
use App\Services\DocumentTemplateService;
use App\Services\DocumentoService;
use App\Services\DocumentoVersionService;
use App\Services\LimitePlanService;
use App\Services\TemplateVariableService;
use App\Validators\DocumentoValidator;
use App\Validators\DocumentoVersionValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class DocumentTemplateServiceTest extends TestCase
{
    private PDO $pdo;
    private DocumentTemplateService $service;
    private int $firmaId;
    private int $casoId;
    private int $usuarioId;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();
        [$this->firmaId, $this->casoId, $this->usuarioId] = $this->tenant();
        $session = new Session(['secure' => false]);
        (new ReflectionProperty(Session::class, 'started'))->setValue($session, true);
        $_SESSION['auth_user'] = ['id' => $this->usuarioId, 'firma_id' => $this->firmaId, 'permissions' => ['*']];
        $auth = new Auth($session);
        $database = $this->database();
        $audit = new AuditoriaService(new AuditoriaRepository($this->pdo), $auth);
        $documentos = new DocumentoService(
            new DocumentoRepository($this->pdo),
            new DocumentoVersionRepository($this->pdo),
            new ClienteRepository($this->pdo),
            new CasoRepository($this->pdo),
            new GastoRepository($this->pdo),
            new DocumentoValidator(),
            new DocumentoVersionService(
                new DocumentoVersionRepository($this->pdo),
                new DocumentoRepository($this->pdo),
                new DocumentoVersionValidator(),
                $database,
                $audit,
                $auth
            ),
            $this->limitePlanService(),
            $database,
            $audit,
            $auth
        );
        $variables = new TemplateVariableService(
            new CasoRepository($this->pdo),
            new ClienteRepository($this->pdo),
            new FirmaRepository($this->pdo),
            new UsuarioRepository($this->pdo)
        );
        $this->service = new DocumentTemplateService(new DocumentTemplateRepository($this->pdo), $variables, $documentos);
    }

    public function test_create_detects_variables_used(): void
    {
        $template = $this->service->create($this->firmaId, $this->data('<p>{{caso.titulo}}</p>'));

        self::assertSame(['caso.titulo'], $template['variables_usadas']);
    }

    public function test_update_refreshes_variables_used(): void
    {
        $template = $this->service->create($this->firmaId, $this->data('<p>{{caso.titulo}}</p>'));
        $updated = $this->service->update((int) $template['id'], $this->firmaId, $this->data('<p>{{cliente.nombre}}</p>'));

        self::assertSame(['cliente.nombre'], $updated['variables_usadas']);
    }

    public function test_generate_creates_documento_in_caso(): void
    {
        $template = $this->service->create($this->firmaId, $this->data('<p>{{caso.titulo}}</p>'));

        $result = $this->service->generate((int) $template['id'], $this->firmaId, $this->casoId, $this->usuarioId, [], $this->request());

        self::assertGreaterThan(0, $result['documento_id']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM documentos')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM documento_versiones')->fetchColumn());
    }

    public function test_generate_resolves_all_variables(): void
    {
        $template = $this->service->create($this->firmaId, $this->data('<p>{{caso.titulo}} - {{cliente.nombre}}</p>'));

        $result = $this->service->generate((int) $template['id'], $this->firmaId, $this->casoId, $this->usuarioId, [], $this->request());

        self::assertStringContainsString('Caso Principal', (string) $result['contenido']);
        self::assertStringContainsString('Cliente Principal', (string) $result['contenido']);
    }

    public function test_tenant_isolation_cannot_use_other_firma_template(): void
    {
        $template = $this->service->create($this->firmaId, $this->data('<p>{{caso.titulo}}</p>'));
        [$otherFirma] = $this->tenant('B');

        $this->expectException(\App\Core\HttpException::class);
        $this->service->generate((int) $template['id'], $otherFirma, $this->casoId, $this->usuarioId, [], $this->request());
    }

    public function test_generate_with_custom_vars(): void
    {
        $template = $this->service->create($this->firmaId, $this->data('<p>{{custom.campo_1}}</p>'));

        $result = $this->service->generate((int) $template['id'], $this->firmaId, $this->casoId, $this->usuarioId, ['campo_1' => 'Valor especial'], $this->request());

        self::assertStringContainsString('Valor especial', (string) $result['contenido']);
    }

    /** @return array<string, mixed> */
    private function data(string $content): array
    {
        return ['nombre' => 'Contrato Base', 'categoria' => 'Contratos', 'descripcion' => 'Base', 'contenido' => $content, 'activo' => 1];
    }

    /** @return array{int, int, int} */
    private function tenant(string $suffix = 'A'): array
    {
        $this->pdo->prepare('INSERT INTO firmas (uuid,nombre,slug,estado,timezone) VALUES (?, ?, ?, ?, ?)')->execute(['uuid-' . $suffix, 'Firma ' . $suffix, 'firma-' . strtolower($suffix), 'activa', 'UTC']);
        $firmaId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO usuarios (firma_id,nombre,email,email_normalizado,tipo,estado,cargo) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$firmaId, 'Abogado ' . $suffix, 'abogado' . $suffix . '@example.com', 'abogado' . $suffix . '@example.com', 'interno', 'activo', 'Socio']);
        $userId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO clientes (firma_id,nombre_razon_social,nombre_normalizado,tipo_documento,numero_documento,email,telefono,direccion,estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$firmaId, 'Cliente Principal', 'cliente principal', 'CC', '123', 'cliente@example.com', '300', 'Calle 1', 'activo']);
        $clientId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO casos (firma_id,cliente_id,responsable_usuario_id,titulo,titulo_normalizado,descripcion,estado,prioridad,fecha_apertura) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$firmaId, $clientId, $userId, 'Caso Principal', 'caso principal', 'Descripcion', 'activo', 'media', '2026-06-01']);

        return [$firmaId, (int) $this->pdo->lastInsertId(), $userId];
    }

    private function database(): Database
    {
        $database = new Database(['driver' => 'mysql']);
        (new ReflectionProperty(Database::class, 'connection'))->setValue($database, $this->pdo);

        return $database;
    }

    private function request(): Request
    {
        return new Request('POST', '/plantillas/1/generar');
    }

    private function limitePlanService(): LimitePlanService
    {
        $reflection = new ReflectionClass(LimitePlanService::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function schema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE firmas (id INTEGER PRIMARY KEY AUTOINCREMENT,uuid TEXT,nombre TEXT,slug TEXT,estado TEXT,timezone TEXT,deleted_at TEXT);
            CREATE TABLE usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre TEXT,email TEXT,email_normalizado TEXT,tipo TEXT,estado TEXT,cargo TEXT,deleted_at TEXT);
            CREATE TABLE clientes (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre_razon_social TEXT,nombre_normalizado TEXT,tipo_documento TEXT,numero_documento TEXT,email TEXT,telefono TEXT,direccion TEXT,estado TEXT,deleted_at TEXT);
            CREATE TABLE casos (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,cliente_id INTEGER,responsable_usuario_id INTEGER,titulo TEXT,titulo_normalizado TEXT,descripcion TEXT,estado TEXT,prioridad TEXT,tipo_proceso TEXT,fecha_apertura TEXT,deleted_at TEXT);
            CREATE TABLE document_templates (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre TEXT,descripcion TEXT,categoria TEXT,contenido TEXT,variables_usadas TEXT,activo INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,deleted_at TEXT);
            CREATE TABLE documentos (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,cliente_id INTEGER,caso_id INTEGER,gasto_id INTEGER,current_version_id INTEGER,titulo TEXT,titulo_normalizado TEXT,descripcion TEXT,tipo_documental TEXT,estado TEXT,visible_portal INTEGER,created_by_usuario_id INTEGER,created_at TEXT,updated_at TEXT,deleted_at TEXT);
            CREATE TABLE documento_versiones (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,documento_id INTEGER,version_numero INTEGER,nombre_original TEXT,nombre_fisico TEXT,extension TEXT,mime_declarado TEXT,mime_detectado TEXT,size_bytes INTEGER,checksum_sha256 TEXT,storage_path TEXT,uploaded_by_usuario_id INTEGER,created_at TEXT);
            CREATE TABLE auditoria (id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,usuario_id INTEGER,accion TEXT,modulo TEXT,entidad_tipo TEXT,entidad_id TEXT,severidad TEXT,correlation_id TEXT,ip_address TEXT,user_agent TEXT,metadata TEXT,created_at TEXT);'
        );
    }
}
