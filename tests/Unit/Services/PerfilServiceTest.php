<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Repositories\PerfilRepository;
use App\Repositories\UsuarioCambiosSensiblesRepository;
use App\Services\AuditoriaService;
use App\Services\PerfilService;
use App\Services\SensitiveDataService;
use App\Services\UsuarioCambiosSensiblesService;
use App\Validators\PerfilValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * PerfilService no tenia ningun test antes del 2026-07-11, a pesar de manejar
 * datos personales y profesionales sensibles de "Mi perfil" (Sesiones 1-6). Este
 * archivo cubre su comportamiento preexistente sin cambiarlo; tambien blinda el
 * refactor de recordSensitiveChange() para que delegue en
 * UsuarioCambiosSensiblesService (Sesion 8) sin alterar el resultado observable.
 */
final class PerfilServiceTest extends TestCase
{
    private PDO $pdo;
    private PerfilService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();
        $this->seed();

        $_SESSION = [];
        $session = new Session(['name' => 'legalops_test_' . bin2hex(random_bytes(4)), 'secure' => false]);
        $auth = new Auth($session);
        $auth->login(['id' => 10, 'firma_id' => 1, 'name' => 'Usuario Prueba', 'permissions' => ['perfil.*']]);

        $sensitive = new SensitiveDataService();
        $audit = new AuditoriaService(new AuditoriaRepository($this->pdo), $auth);
        $cambiosSensibles = new UsuarioCambiosSensiblesService(new UsuarioCambiosSensiblesRepository($this->pdo), $sensitive);

        $this->service = new PerfilService(
            new PerfilRepository($this->pdo),
            new PerfilValidator(),
            $this->database(),
            $audit,
            $auth,
            $sensitive,
            $cambiosSensibles
        );
    }

    public function test_own_returns_masked_document(): void
    {
        $profile = $this->service->own();

        self::assertSame('Usuario Prueba', $profile['nombre']);
        self::assertStringContainsString('*', (string) $profile['numero_documento_enmascarado']);
    }

    public function test_update_personal_changes_fields_and_records_sensitive_change(): void
    {
        $this->service->updatePersonal([
            'nombres' => 'Ana',
            'apellidos' => 'Gomez',
            'tipo_documento_id' => 1,
            'numero_documento' => '999888777',
            'telefono' => '3001234567',
        ], $this->request());

        $usuario = $this->pdo->query('SELECT nombres, apellidos, numero_documento_normalizado FROM usuarios WHERE id=10')->fetch();
        self::assertSame('Ana', $usuario['nombres']);
        self::assertSame('999888777', $usuario['numero_documento_normalizado']);

        $historial = $this->pdo->query("SELECT * FROM usuario_cambios_sensibles WHERE campo='numero_documento'")->fetch();
        self::assertNotFalse($historial);
        self::assertSame('mi_perfil', $historial['origen']);
    }

    public function test_update_personal_rejects_duplicate_document(): void
    {
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, nombres, apellidos, email, email_normalizado, email_scope, password_hash, tipo, estado, tipo_documento_id, numero_documento, numero_documento_normalizado)
             VALUES (11, 1, 'Otro Usuario', 'Otro', 'Usuario', 'otro@correo.com', 'otro@correo.com', 'firma:1:otro@correo.com', 'hash', 'interno', 'activo', 1, '555000111', '555000111')"
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('El documento ya está registrado para otro usuario de la firma.');

        $this->service->updatePersonal([
            'nombres' => 'Ana',
            'apellidos' => 'Gomez',
            'tipo_documento_id' => 1,
            'numero_documento' => '555000111',
            'telefono' => '3001234567',
        ], $this->request());
    }

    public function test_update_personal_rejects_unexpected_fields(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Mi perfil solo permite modificar información personal autorizada.');

        $this->service->updatePersonal([
            'nombres' => 'Ana',
            'apellidos' => 'Gomez',
            'tipo_documento_id' => 1,
            'numero_documento' => '999888777',
            'telefono' => '3001234567',
            'estado' => 'inactivo',
        ], $this->request());
    }

    public function test_update_professional_requires_card_number_when_has_card(): void
    {
        $this->expectException(HttpException::class);

        $this->service->updateProfessional([
            'es_abogado' => 1,
            'tiene_tarjeta_profesional' => 1,
            'numero_tarjeta_profesional' => '',
        ], $this->request());
    }

    public function test_update_professional_sets_pending_verification_state(): void
    {
        $this->service->updateProfessional([
            'es_abogado' => 1,
            'tiene_tarjeta_profesional' => 1,
            'numero_tarjeta_profesional' => 'ABC-123',
        ], $this->request());

        $usuario = $this->pdo->query('SELECT tarjeta_profesional_verificacion_estado FROM usuarios WHERE id=10')->fetch();
        self::assertSame('pendiente', $usuario['tarjeta_profesional_verificacion_estado']);
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('La contraseña actual no es correcta.');

        $this->service->changePassword([
            'password_actual' => 'incorrecta',
            'password_nuevo' => 'NuevaClave#2026',
            'password_confirmacion' => 'NuevaClave#2026',
        ], $this->request());
    }

    public function test_change_password_rejects_weak_password(): void
    {
        $this->expectException(HttpException::class);

        $this->service->changePassword([
            'password_actual' => 'ClaveActual#2026',
            'password_nuevo' => 'debil',
            'password_confirmacion' => 'debil',
        ], $this->request());
    }

    public function test_change_password_success_updates_hash(): void
    {
        $this->service->changePassword([
            'password_actual' => 'ClaveActual#2026',
            'password_nuevo' => 'NuevaClave#2026',
            'password_confirmacion' => 'NuevaClave#2026',
        ], $this->request());

        $hash = $this->pdo->query('SELECT password_hash FROM usuarios WHERE id=10')->fetchColumn();
        self::assertTrue(password_verify('NuevaClave#2026', (string) $hash));
    }

    private function request(): Request
    {
        return new Request(
            method: 'POST',
            uri: '/mi-perfil',
            headers: ['user-agent' => 'PHPUnit'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );
    }

    private function database(): Database
    {
        $database = new Database(['driver' => 'mysql']);
        (new ReflectionProperty(Database::class, 'connection'))->setValue($database, $this->pdo);

        return $database;
    }

    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO catalogos (id, scope_key, firma_id, estado) VALUES (1, 'global:tipo_documento', NULL, 'activo')");
        $this->pdo->exec("INSERT INTO catalogo_items (id, catalogo_id, firma_id, estado, codigo, etiqueta, orden) VALUES (1, 1, NULL, 'activo', 'CC', 'Cédula de ciudadanía', 1)");
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, nombres, apellidos, email, email_normalizado, email_scope, password_hash, tipo, estado, numero_documento, numero_documento_normalizado)
             VALUES (10, 1, 'Usuario Prueba', 'Usuario', 'Prueba', 'usuario@correo.com', 'usuario@correo.com', 'firma:1:usuario@correo.com', 'x', 'interno', 'activo', '100200300', '100200300')"
        );
        $update = $this->pdo->prepare('UPDATE usuarios SET password_hash=:hash WHERE id=10');
        $update->execute(['hash' => password_hash('ClaveActual#2026', PASSWORD_BCRYPT, ['cost' => 12])]);
    }

    private function schema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NULL,
                nombre TEXT NOT NULL,
                nombres TEXT NULL,
                apellidos TEXT NULL,
                tipo_documento_id INTEGER NULL,
                numero_documento TEXT NULL,
                numero_documento_normalizado TEXT NULL,
                telefono TEXT NULL,
                cargo TEXT NULL,
                foto_perfil_path TEXT NULL,
                email TEXT NOT NULL,
                email_normalizado TEXT NOT NULL,
                email_scope TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                tipo TEXT NOT NULL DEFAULT \'interno\',
                estado TEXT NOT NULL DEFAULT \'activo\',
                es_abogado INTEGER NOT NULL DEFAULT 0,
                tiene_tarjeta_profesional INTEGER NOT NULL DEFAULT 0,
                numero_tarjeta_profesional TEXT NULL,
                numero_tarjeta_profesional_normalizado TEXT NULL,
                tarjeta_profesional_verificacion_estado TEXT NULL,
                fecha_verificacion_tarjeta TEXT NULL,
                usuario_verificador_tarjeta_id INTEGER NULL,
                observacion_verificacion_tarjeta TEXT NULL,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                last_login_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE catalogos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                scope_key TEXT NOT NULL,
                firma_id INTEGER NULL,
                estado TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE catalogo_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalogo_id INTEGER NOT NULL,
                firma_id INTEGER NULL,
                estado TEXT NOT NULL,
                codigo TEXT NOT NULL,
                etiqueta TEXT NOT NULL,
                orden INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT NULL
            );
            CREATE TABLE usuario_roles (
                firma_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                rol_id INTEGER NOT NULL,
                created_at TEXT NULL
            );
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                codigo TEXT NOT NULL,
                nombre TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE auditoria (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NULL,
                usuario_id INTEGER NULL,
                accion TEXT NOT NULL,
                modulo TEXT NOT NULL,
                entidad_tipo TEXT NULL,
                entidad_id TEXT NULL,
                severidad TEXT NOT NULL DEFAULT \'info\',
                correlation_id TEXT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                metadata TEXT NULL,
                created_at TEXT NULL
            );
            CREATE TABLE usuario_cambios_sensibles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                usuario_afectado_id INTEGER NOT NULL,
                usuario_actor_id INTEGER NULL,
                campo TEXT NOT NULL,
                valor_anterior_enmascarado TEXT NULL,
                valor_nuevo_enmascarado TEXT NULL,
                valor_anterior_hash TEXT NULL,
                valor_nuevo_hash TEXT NULL,
                origen TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                created_at TEXT NULL DEFAULT CURRENT_TIMESTAMP
            );'
        );
    }
}
