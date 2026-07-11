<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Clase base para tests de Repositories.
 *
 * Usa SQLite :memory: para que los tests sean rápidos y no dependan de MySQL.
 * El esquema replica las tablas reales del sistema pero con tipos compatibles SQLite.
 *
 * IMPORTANTE: Cada test hereda de esta clase para obtener una BD limpia.
 */
abstract class RepositoryTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        // SQLite :memory: se destruye con el objeto PDO
        unset($this->pdo);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schema SQLite (equivalente al MySQL del sistema)
    // ─────────────────────────────────────────────────────────────────────────

    private function createSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE firmas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                slug TEXT,
                email TEXT,
                telefono TEXT,
                pais TEXT DEFAULT 'MX',
                timezone TEXT DEFAULT 'America/Mexico_City',
                suspended_at TEXT,
                suspension_reason TEXT,
                deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER,
                nombre TEXT NOT NULL,
                email TEXT NOT NULL,
                email_normalizado TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                rol TEXT DEFAULT 'abogado',
                activo INTEGER DEFAULT 1,
                must_change_password INTEGER DEFAULT 0,
                deactivated_at TEXT,
                deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (firma_id) REFERENCES firmas(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                nombre_razon_social TEXT NOT NULL,
                tipo_persona TEXT DEFAULT 'fisica',
                numero_documento TEXT,
                tipo_documento TEXT,
                email TEXT NOT NULL,
                telefono TEXT,
                direccion TEXT,
                estado TEXT DEFAULT 'activo',
                deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (firma_id) REFERENCES firmas(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE prospectos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                nombre_empresa TEXT NOT NULL,
                nombre_contacto TEXT,
                email_contacto TEXT NOT NULL,
                telefono TEXT,
                estado TEXT DEFAULT 'nuevo',
                fuente TEXT,
                notas TEXT,
                asignado_a INTEGER,
                deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (firma_id) REFERENCES firmas(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE casos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                abogado_id INTEGER,
                numero_caso TEXT,
                titulo TEXT NOT NULL,
                descripcion TEXT NOT NULL,
                estado TEXT DEFAULT 'activo',
                prioridad TEXT DEFAULT 'media',
                tipo TEXT,
                fecha_inicio TEXT,
                fecha_cierre TEXT,
                deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (firma_id) REFERENCES firmas(id),
                FOREIGN KEY (cliente_id) REFERENCES clientes(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                caso_id INTEGER NOT NULL,
                asignado_a INTEGER,
                titulo TEXT NOT NULL,
                descripcion TEXT,
                estado TEXT DEFAULT 'pendiente',
                prioridad TEXT DEFAULT 'media',
                fecha_vencimiento TEXT NOT NULL,
                completada_at TEXT,
                deleted_at TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                updated_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (firma_id) REFERENCES firmas(id),
                FOREIGN KEY (caso_id) REFERENCES casos(id)
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers de inserción
    // ─────────────────────────────────────────────────────────────────────────

    protected function insertFirma(string $nombre = 'Firma Test'): int
    {
        $this->pdo->prepare(
            'INSERT INTO firmas (nombre, slug, email, created_at, updated_at)
             VALUES (?, ?, ?, datetime("now"), datetime("now"))'
        )->execute([$nombre, strtolower(str_replace(' ', '-', $nombre)), 'test@' . strtolower(str_replace(' ', '', $nombre)) . '.mx']);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertUser(int $firmaId, string $email = 'admin@test.mx'): int
    {
        $this->pdo->prepare(
            'INSERT INTO usuarios (firma_id, nombre, email, email_normalizado, password, rol, created_at, updated_at)
             VALUES (?, "Admin Test", ?, ?, "hashed_pass", "admin", datetime("now"), datetime("now"))'
        )->execute([$firmaId, $email, $email]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function insertCliente(int $firmaId, array $overrides = []): int
    {
        $data = array_merge([
            'nombre_razon_social' => 'Cliente de Prueba S.A.',
            'email'               => 'cliente@test.mx',
            'tipo_persona'        => 'moral',
            'estado'              => 'activo',
        ], $overrides);

        $this->pdo->prepare(
            'INSERT INTO clientes (firma_id, nombre_razon_social, email, tipo_persona, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, datetime("now"), datetime("now"))'
        )->execute([
            $firmaId,
            $data['nombre_razon_social'],
            $data['email'],
            $data['tipo_persona'],
            $data['estado'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertCaso(int $firmaId, int $clienteId, array $overrides = []): int
    {
        $data = array_merge([
            'titulo'      => 'Caso de Prueba',
            'descripcion' => 'Descripción del caso de prueba.',
            'estado'      => 'activo',
            'prioridad'   => 'media',
        ], $overrides);

        $this->pdo->prepare(
            'INSERT INTO casos (firma_id, cliente_id, titulo, descripcion, estado, prioridad, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime("now"), datetime("now"))'
        )->execute([
            $firmaId,
            $clienteId,
            $data['titulo'],
            $data['descripcion'],
            $data['estado'],
            $data['prioridad'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertProspecto(int $firmaId, array $overrides = []): int
    {
        $data = array_merge([
            'nombre_empresa'  => 'Empresa Prospecto S.A.',
            'email_contacto'  => 'contacto@prospecto.mx',
            'estado'          => 'nuevo',
        ], $overrides);

        $this->pdo->prepare(
            'INSERT INTO prospectos (firma_id, nombre_empresa, email_contacto, estado, created_at, updated_at)
             VALUES (?, ?, ?, ?, datetime("now"), datetime("now"))'
        )->execute([$firmaId, $data['nombre_empresa'], $data['email_contacto'], $data['estado']]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertTarea(int $firmaId, int $casoId, array $overrides = []): int
    {
        $data = array_merge([
            'titulo'            => 'Tarea de Prueba',
            'estado'            => 'pendiente',
            'prioridad'         => 'media',
            'fecha_vencimiento' => date('Y-m-d', strtotime('+7 days')),
        ], $overrides);

        $this->pdo->prepare(
            'INSERT INTO tareas (firma_id, caso_id, titulo, estado, prioridad, fecha_vencimiento, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime("now"), datetime("now"))'
        )->execute([
            $firmaId,
            $casoId,
            $data['titulo'],
            $data['estado'],
            $data['prioridad'],
            $data['fecha_vencimiento'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
