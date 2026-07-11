<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AuditoriaRepository;
use App\Repositories\PlanRepository;
use App\Repositories\RolRepository;
use App\Repositories\UserSessionRepository;
use App\Repositories\UsuarioCambiosSensiblesRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditoriaService;
use App\Services\LimitePlanService;
use App\Services\SensitiveDataService;
use App\Services\UsuarioCambiosSensiblesService;
use App\Services\UsuarioService;
use App\Validators\UsuarioValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Cubre las adiciones de la Sesion 7 (consola administrativa de Usuarios) y
 * Sesion 8 (auditoria integral) del modulo "Mi perfil": updateCargo,
 * updateAccount, revokeSessions, pendingProfessionalCards e history.
 *
 * Tambien cubre, como parte del cierre de huecos de testing preexistentes,
 * update(), deactivate(), reactivate() y verifyProfessionalCard(), que no
 * tenian ningun test antes de esta sesion. create() queda pendiente: requiere
 * el esquema completo de limites de plan (LimitePlanService/PlanRepository),
 * fuera de alcance de esta ronda por tamaño; documentado en
 * 01_DOCS/32_CAMINO_A_V1.md.
 */
final class UsuarioServiceTest extends TestCase
{
    private PDO $pdo;
    private UsuarioService $service;

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
        $auth->login(['id' => 99, 'firma_id' => 1, 'permissions' => ['usuarios.*']]);

        $sensitive = new SensitiveDataService();
        $audit = new AuditoriaService(new AuditoriaRepository($this->pdo), $auth);
        $cambiosSensibles = new UsuarioCambiosSensiblesService(new UsuarioCambiosSensiblesRepository($this->pdo), $sensitive);

        $this->service = new UsuarioService(
            new UsuarioRepository($this->pdo),
            new RolRepository($this->pdo),
            new UserSessionRepository($this->pdo),
            new UsuarioValidator(),
            new LimitePlanService(new PlanRepository($this->pdo), $audit, $auth),
            $this->database(),
            $audit,
            $auth,
            $sensitive,
            $cambiosSensibles
        );
    }

    // --- Cobertura agregada para funciones preexistentes sin tests antes de hoy ---

    public function test_update_changes_nombre_email_tipo_y_roles(): void
    {
        $this->pdo->exec("INSERT INTO roles (id, firma_id, codigo, nombre, estado, is_protected) VALUES (2, 1, 'asistente', 'Asistente', 'activo', 0)");

        $this->service->update(1, 10, [
            'nombre' => 'Usuario Renombrado',
            'email' => 'renombrado@correo.com',
            'tipo' => 'interno',
            'estado' => 'activo',
        ], [2], $this->request());

        $usuario = $this->pdo->query('SELECT nombre, email FROM usuarios WHERE id=10')->fetch();
        self::assertSame('Usuario Renombrado', $usuario['nombre']);
        self::assertSame('renombrado@correo.com', $usuario['email']);

        $rolesAsignados = (int) $this->pdo->query('SELECT COUNT(*) FROM usuario_roles WHERE usuario_id=10 AND rol_id=2')->fetchColumn();
        self::assertSame(1, $rolesAsignados);
    }

    public function test_update_rejects_duplicate_email_in_same_firma(): void
    {
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, email, email_normalizado, email_scope, password_hash, tipo, estado)
             VALUES (13, 1, 'Otro', 'ocupado@correo.com', 'ocupado@correo.com', 'firma:1:ocupado@correo.com', 'x', 'interno', 'activo')"
        );

        $this->expectException(HttpException::class);

        $this->service->update(1, 10, [
            'nombre' => 'Usuario Prueba',
            'email' => 'ocupado@correo.com',
            'tipo' => 'interno',
            'estado' => 'activo',
        ], [], $this->request());
    }

    public function test_deactivate_revokes_sessions_and_sets_estado_inactivo(): void
    {
        $this->pdo->exec(
            "INSERT INTO user_sessions (firma_id, usuario_id, session_hash, fingerprint_hash, ip_address, user_agent, expires_at, created_at)
             VALUES (1, 10, 'hash', 'fp', '127.0.0.1', 'agent', '2999-01-01 00:00:00', CURRENT_TIMESTAMP)"
        );

        $this->service->deactivate(1, 10, $this->request());

        $usuario = $this->pdo->query('SELECT estado FROM usuarios WHERE id=10')->fetch();
        self::assertSame('inactivo', $usuario['estado']);
        $revokedAt = $this->pdo->query('SELECT revoked_at FROM user_sessions WHERE usuario_id=10')->fetchColumn();
        self::assertNotNull($revokedAt);
    }

    public function test_deactivate_cannot_leave_firma_without_active_admin(): void
    {
        $this->pdo->exec("INSERT INTO roles (id, firma_id, codigo, nombre, estado, is_protected) VALUES (1, 1, 'administrador', 'Administrador', 'activo', 1)");
        $this->pdo->exec('INSERT INTO usuario_roles (firma_id, usuario_id, rol_id) VALUES (1, 10, 1)');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No se puede dejar la firma sin un administrador activo.');

        $this->service->deactivate(1, 10, $this->request());
    }

    public function test_reactivate_sets_estado_activo(): void
    {
        $this->service->deactivate(1, 10, $this->request());
        $this->service->reactivate(1, 10, $this->request());

        $usuario = $this->pdo->query('SELECT estado FROM usuarios WHERE id=10')->fetch();
        self::assertSame('activo', $usuario['estado']);
    }

    public function test_verify_professional_card_marks_verificada(): void
    {
        $this->pdo->exec(
            "UPDATE usuarios SET tiene_tarjeta_profesional=1, numero_tarjeta_profesional_normalizado='ABC123', tarjeta_profesional_verificacion_estado='pendiente' WHERE id=10"
        );

        $this->service->verifyProfessionalCard(1, 10, ['estado' => 'verificada'], $this->request());

        $usuario = $this->pdo->query('SELECT tarjeta_profesional_verificacion_estado, usuario_verificador_tarjeta_id FROM usuarios WHERE id=10')->fetch();
        self::assertSame('verificada', $usuario['tarjeta_profesional_verificacion_estado']);
        self::assertSame(99, (int) $usuario['usuario_verificador_tarjeta_id']);
    }

    public function test_verify_professional_card_rejects_self_verification(): void
    {
        $this->pdo->exec(
            "UPDATE usuarios SET tiene_tarjeta_profesional=1, numero_tarjeta_profesional_normalizado='ABC123', tarjeta_profesional_verificacion_estado='pendiente' WHERE id=10"
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('El titular no puede verificar su propia tarjeta profesional.');

        // El actor autenticado en el setUp() es id=99. Pasar 99 como usuario objetivo
        // simula que un usuario intenta verificar su propia tarjeta profesional.
        $this->service->verifyProfessionalCard(1, 99, ['estado' => 'verificada'], $this->request());
    }

    public function test_update_cargo_changes_field_and_records_history(): void
    {
        $this->service->updateCargo(1, 10, 'Abogado Senior', $this->request());

        $cargo = $this->pdo->query('SELECT cargo FROM usuarios WHERE id=10')->fetchColumn();
        self::assertSame('Abogado Senior', $cargo);

        $historial = $this->service->history(1, 10);
        self::assertSame(1, $historial['total']);
        self::assertSame('cargo', $historial['items'][0]['campo']);
        self::assertSame('usuarios', $historial['items'][0]['origen']);
    }

    public function test_update_cargo_repeated_value_does_not_duplicate_history(): void
    {
        $this->service->updateCargo(1, 10, 'Abogado Senior', $this->request());
        $this->service->updateCargo(1, 10, 'Abogado Senior', $this->request());

        $historial = $this->service->history(1, 10);
        self::assertSame(1, $historial['total']);
    }

    public function test_update_account_changes_email_tipo_estado_and_revokes_sessions(): void
    {
        $this->pdo->exec(
            "INSERT INTO user_sessions (firma_id, usuario_id, session_hash, fingerprint_hash, ip_address, user_agent, expires_at, created_at)
             VALUES (1, 10, 'hash', 'fp', '127.0.0.1', 'agent', '2999-01-01 00:00:00', CURRENT_TIMESTAMP)"
        );

        $this->service->updateAccount(1, 10, [
            'email' => 'nuevo@correo.com',
            'tipo' => 'cliente_externo',
            'estado' => 'inactivo',
        ], $this->request());

        $usuario = $this->pdo->query('SELECT email, tipo, estado FROM usuarios WHERE id=10')->fetch();
        self::assertSame('nuevo@correo.com', $usuario['email']);
        self::assertSame('cliente_externo', $usuario['tipo']);
        self::assertSame('inactivo', $usuario['estado']);

        $revokedAt = $this->pdo->query('SELECT revoked_at FROM user_sessions WHERE usuario_id=10')->fetchColumn();
        self::assertNotNull($revokedAt);

        $historial = $this->service->history(1, 10);
        self::assertSame(3, $historial['total']);
    }

    public function test_update_account_cannot_leave_firma_without_active_admin(): void
    {
        $this->pdo->exec("INSERT INTO roles (id, firma_id, codigo, nombre, estado, is_protected) VALUES (1, 1, 'administrador', 'Administrador', 'activo', 1)");
        $this->pdo->exec('INSERT INTO usuario_roles (firma_id, usuario_id, rol_id) VALUES (1, 10, 1)');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No se puede dejar la firma sin un administrador activo.');

        $this->service->updateAccount(1, 10, ['estado' => 'inactivo'], $this->request());
    }

    public function test_revoke_sessions_does_not_deactivate_user(): void
    {
        $this->pdo->exec(
            "INSERT INTO user_sessions (firma_id, usuario_id, session_hash, fingerprint_hash, ip_address, user_agent, expires_at, created_at)
             VALUES (1, 10, 'hash', 'fp', '127.0.0.1', 'agent', '2999-01-01 00:00:00', CURRENT_TIMESTAMP)"
        );

        $this->service->revokeSessions(1, 10, $this->request());

        $usuario = $this->pdo->query('SELECT estado FROM usuarios WHERE id=10')->fetch();
        self::assertSame('activo', $usuario['estado']);

        $revokedAt = $this->pdo->query('SELECT revoked_at FROM user_sessions WHERE usuario_id=10')->fetchColumn();
        self::assertNotNull($revokedAt);
    }

    public function test_pending_professional_cards_filters_by_firma_and_estado(): void
    {
        $this->pdo->exec(
            "UPDATE usuarios SET tiene_tarjeta_profesional=1, numero_tarjeta_profesional_normalizado='ABC123', tarjeta_profesional_verificacion_estado='pendiente' WHERE id=10"
        );
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, email, email_normalizado, email_scope, password_hash, tipo, estado, tiene_tarjeta_profesional, numero_tarjeta_profesional_normalizado, tarjeta_profesional_verificacion_estado)
             VALUES (11, 1, 'Ya Verificado', 'verificado@correo.com', 'verificado@correo.com', 'firma:1:verificado@correo.com', 'x', 'interno', 'activo', 1, 'XYZ999', 'verificada')"
        );
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, email, email_normalizado, email_scope, password_hash, tipo, estado, tiene_tarjeta_profesional, numero_tarjeta_profesional_normalizado, tarjeta_profesional_verificacion_estado)
             VALUES (12, 2, 'Otra Firma', 'otrafirma@correo.com', 'otrafirma@correo.com', 'firma:2:otrafirma@correo.com', 'x', 'interno', 'activo', 1, 'QWE111', 'pendiente')"
        );

        $pendientes = $this->service->pendingProfessionalCards(1);

        self::assertCount(1, $pendientes);
        self::assertSame(10, (int) $pendientes[0]['id']);
    }

    public function test_history_is_isolated_by_firma(): void
    {
        $this->service->updateCargo(1, 10, 'Abogado Senior', $this->request());

        try {
            // El mismo id de usuario, pero consultado desde otra firma: debe rechazarse.
            $this->service->history(2, 10);
            self::fail('Se esperaba HttpException al consultar el historial desde otra firma.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->status());
        }
    }

    private function request(): \App\Core\Request
    {
        return new \App\Core\Request(
            method: 'POST',
            uri: '/usuarios/10',
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
        $this->pdo->exec(
            "INSERT INTO usuarios (id, firma_id, nombre, email, email_normalizado, email_scope, password_hash, tipo, estado, cargo)
             VALUES (10, 1, 'Usuario Prueba', 'usuario@correo.com', 'usuario@correo.com', 'firma:1:usuario@correo.com', 'hash', 'interno', 'activo', NULL)"
        );
    }

    private function schema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                nombre TEXT NOT NULL,
                email TEXT NOT NULL,
                email_normalizado TEXT NOT NULL,
                email_scope TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                tipo TEXT NOT NULL DEFAULT \'interno\',
                estado TEXT NOT NULL DEFAULT \'activo\',
                cargo TEXT NULL,
                tiene_tarjeta_profesional INTEGER NOT NULL DEFAULT 0,
                numero_tarjeta_profesional TEXT NULL,
                numero_tarjeta_profesional_normalizado TEXT NULL,
                tarjeta_profesional_verificacion_estado TEXT NULL,
                fecha_verificacion_tarjeta TEXT NULL,
                usuario_verificador_tarjeta_id INTEGER NULL,
                observacion_verificacion_tarjeta TEXT NULL,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                last_login_at TEXT NULL,
                invited_at TEXT NULL,
                deactivated_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                codigo TEXT NOT NULL,
                nombre TEXT NOT NULL,
                descripcion TEXT NULL,
                estado TEXT NOT NULL DEFAULT \'activo\',
                is_protected INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE usuario_roles (
                firma_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                rol_id INTEGER NOT NULL,
                created_at TEXT NULL
            );
            CREATE TABLE user_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NULL,
                usuario_id INTEGER NOT NULL,
                session_hash TEXT NOT NULL,
                fingerprint_hash TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                last_activity_at TEXT NULL,
                expires_at TEXT NOT NULL,
                revoked_at TEXT NULL,
                revoked_reason TEXT NULL,
                created_at TEXT NULL
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
            );
            CREATE TABLE planes (
                id INTEGER PRIMARY KEY AUTOINCREMENT
            );'
        );
    }
}
