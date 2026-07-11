<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Repositories\PerfilRepository;
use App\Services\AuditoriaService;
use App\Services\PerfilService;
use App\Services\SensitiveDataService;
use App\Validators\PerfilValidator;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('perfil_password')]
class ChangePasswordServiceTest extends TestCase
{
    private PDO $pdo;
    private PerfilService $service;
    private Auth $auth;
    private int $userId = 1;
    private ?int $firmaId = 5;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();

        $_SESSION = [];
        $session = new Session(['name' => 'pw_test_' . bin2hex(random_bytes(4)), 'secure' => false]);
        $this->auth = new Auth($session);
        $this->auth->login([
            'id'       => $this->userId,
            'firma_id' => $this->firmaId,
            'permissions' => ['perfil.editar'],
        ]);

        $this->service = $this->buildService();
        $this->seedUser('Contraseña#Actual1');
    }

    public function test_change_password_happy_path(): void
    {
        $request = $this->fakeRequest();

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'NuevaSegura#2026X',
            'password_confirmacion' => 'NuevaSegura#2026X',
        ], $request);

        $hash = $this->pdo
            ->query('SELECT password_hash FROM usuarios WHERE id = 1')
            ->fetchColumn();

        self::assertTrue(password_verify('NuevaSegura#2026X', (string) $hash));
    }

    public function test_wrong_current_password_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('La contraseña actual no es correcta.');

        $this->service->changePassword([
            'password_actual'       => 'Incorrecta#9999',
            'password_nuevo'        => 'NuevaSegura#2026X',
            'password_confirmacion' => 'NuevaSegura#2026X',
        ], $this->fakeRequest());
    }

    public function test_new_equals_current_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('La nueva contraseña debe ser diferente a la actual.');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'Contraseña#Actual1',
            'password_confirmacion' => 'Contraseña#Actual1',
        ], $this->fakeRequest());
    }

    public function test_confirmation_mismatch_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Las contraseñas no coinciden.');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'NuevaSegura#2026X',
            'password_confirmacion' => 'NuevaSegura#2026Y',
        ], $this->fakeRequest());
    }

    public function test_too_short_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no cumple los requisitos de seguridad');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'Corta#1',
            'password_confirmacion' => 'Corta#1',
        ], $this->fakeRequest());
    }

    public function test_no_uppercase_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no cumple los requisitos de seguridad');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'sinmayuscula#2026x',
            'password_confirmacion' => 'sinmayuscula#2026x',
        ], $this->fakeRequest());
    }

    public function test_no_number_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no cumple los requisitos de seguridad');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'SinNumerosAquiX#',
            'password_confirmacion' => 'SinNumerosAquiX#',
        ], $this->fakeRequest());
    }

    public function test_no_special_char_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('no cumple los requisitos de seguridad');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'SinEspeciales2026X',
            'password_confirmacion' => 'SinEspeciales2026X',
        ], $this->fakeRequest());
    }

    public function test_empty_current_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Debe ingresar su contraseña actual.');

        $this->service->changePassword([
            'password_actual'       => '',
            'password_nuevo'        => 'NuevaSegura#2026X',
            'password_confirmacion' => 'NuevaSegura#2026X',
        ], $this->fakeRequest());
    }

    public function test_unexpected_field_throws_422(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Campos no permitidos');

        $this->service->changePassword([
            'password_actual'       => 'Contraseña#Actual1',
            'password_nuevo'        => 'NuevaSegura#2026X',
            'password_confirmacion' => 'NuevaSegura#2026X',
            'email'                 => 'hack@evil.com',
        ], $this->fakeRequest());
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function buildService(): PerfilService
    {
        // Database is final and connects lazily — changePassword() does not call
        // transaction(), so the connection is never opened during these tests.
        $db = new \App\Core\Database(['driver' => 'sqlite_unused']);

        $perfilRepo       = new PerfilRepository($this->pdo);
        $auditoriaRepo    = new AuditoriaRepository($this->pdo);
        $auditoriaService = new AuditoriaService($auditoriaRepo, $this->auth);
        $sensitive        = new SensitiveDataService();
        $validator        = new PerfilValidator();

        return new PerfilService(
            $perfilRepo,
            $validator,
            $db,
            $auditoriaService,
            $this->auth,
            $sensitive
        );
    }

    private function seedUser(string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, nombres, apellidos,
                                   email, password_hash, tipo, estado, tipo_documento_id,
                                   numero_documento, numero_documento_normalizado)
             VALUES (1, 5, 'Test User', 'Test', 'User',
                     'test@example.com', " . $this->pdo->quote($hash) . ", 'interno', 'activo', NULL, NULL, NULL)"
        );
    }

    private function fakeRequest(): Request
    {
        return new Request('PATCH', '/mi-perfil/contrasena', [], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    }

    private function schema(): void
    {
        $this->pdo->exec('
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY,
                firma_id INTEGER,
                nombre TEXT,
                nombres TEXT,
                apellidos TEXT,
                email TEXT,
                password_hash TEXT,
                tipo TEXT DEFAULT \'interno\',
                estado TEXT DEFAULT \'activo\',
                tipo_documento_id INTEGER,
                numero_documento TEXT,
                numero_documento_normalizado TEXT,
                es_abogado INTEGER DEFAULT 0,
                tiene_tarjeta_profesional INTEGER DEFAULT 0,
                numero_tarjeta_profesional TEXT,
                numero_tarjeta_profesional_normalizado TEXT,
                tarjeta_profesional_verificacion_estado TEXT,
                fecha_verificacion_tarjeta TEXT,
                usuario_verificador_tarjeta_id INTEGER,
                observacion_verificacion_tarjeta TEXT,
                cargo TEXT,
                foto_perfil_path TEXT,
                telefono TEXT,
                last_login_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT,
                deleted_at TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE auditoria (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER,
                usuario_id INTEGER,
                accion TEXT,
                modulo TEXT,
                entidad_tipo TEXT,
                entidad_id INTEGER,
                severidad TEXT DEFAULT \'info\',
                correlation_id TEXT,
                ip_address TEXT,
                user_agent TEXT,
                metadata TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE usuario_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER,
                rol_id INTEGER,
                firma_id INTEGER
            )
        ');

        $this->pdo->exec('
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER,
                nombre TEXT,
                deleted_at TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE catalogo_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                catalogo_id INTEGER,
                firma_id INTEGER,
                codigo TEXT,
                etiqueta TEXT,
                orden INTEGER DEFAULT 0,
                estado TEXT DEFAULT \'activo\',
                deleted_at TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE catalogos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER,
                scope_key TEXT,
                estado TEXT DEFAULT \'activo\',
                deleted_at TEXT
            )
        ');

        $this->pdo->exec('
            CREATE TABLE usuario_cambios_sensibles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER,
                usuario_afectado_id INTEGER,
                usuario_actor_id INTEGER,
                campo TEXT,
                valor_anterior_enmascarado TEXT,
                valor_nuevo_enmascarado TEXT,
                valor_anterior_hash TEXT,
                valor_nuevo_hash TEXT,
                origen TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
}
