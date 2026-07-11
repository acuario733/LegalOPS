<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\HttpException;
use App\Repositories\CasoRepository;
use App\Repositories\TareaRepository;
use App\Repositories\TimeEntryRepository;
use App\Repositories\UsuarioRepository;
use App\Services\TimeEntryService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TimeEntryServiceTest extends TestCase
{
    private PDO $pdo;
    private TimeEntryService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();

        $this->service = new TimeEntryService(
            new TimeEntryRepository($this->pdo),
            new CasoRepository($this->pdo),
            new TareaRepository($this->pdo),
            new UsuarioRepository($this->pdo)
        );
    }

    public function test_create_entry_with_valid_data(): void
    {
        [$firmaId, $usuarioId, $casoId] = $this->createTenant('Firma Uno');

        $entry = $this->service->create($firmaId, $usuarioId, [
            'caso_id' => $casoId,
            'descripcion' => 'Preparación de audiencia',
            'fecha' => date('Y-m-d'),
            'duracion_minutos' => 90,
            'es_facturable' => true,
        ]);

        self::assertGreaterThan(0, (int) $entry['id']);
        self::assertSame($firmaId, (int) $entry['firma_id']);
        self::assertSame(90, (int) $entry['duracion_minutos']);
        self::assertSame(250000.0, (float) $entry['tarifa_hora']);
    }

    public function test_create_entry_rejects_future_date(): void
    {
        [$firmaId, $usuarioId, $casoId] = $this->createTenant('Firma Uno');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('La fecha no puede ser futura.');

        $this->service->create($firmaId, $usuarioId, array_replace($this->validData($casoId), [
            'fecha' => date('Y-m-d', strtotime('+1 day')),
        ]));
    }

    public function test_create_entry_rejects_zero_minutes(): void
    {
        [$firmaId, $usuarioId, $casoId] = $this->createTenant('Firma Uno');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('La duración debe estar entre 1 y 1440 minutos.');

        $this->service->create($firmaId, $usuarioId, array_replace($this->validData($casoId), [
            'duracion_minutos' => 0,
        ]));
    }

    public function test_list_filters_by_firma_id(): void
    {
        [$firmaA, $usuarioA, $casoA] = $this->createTenant('Firma A');
        [$firmaB, $usuarioB, $casoB] = $this->createTenant('Firma B');
        $this->service->create($firmaA, $usuarioA, $this->validData($casoA));
        $this->service->create($firmaB, $usuarioB, $this->validData($casoB));

        $entries = $this->service->list($firmaA, []);

        self::assertCount(1, $entries);
        self::assertSame($firmaA, (int) $entries[0]['firma_id']);
    }

    public function test_delete_soft_deletes(): void
    {
        [$firmaId, $usuarioId, $casoId] = $this->createTenant('Firma Uno');
        $entry = $this->service->create($firmaId, $usuarioId, $this->validData($casoId));

        $this->service->delete((int) $entry['id'], $firmaId);

        $statement = $this->pdo->prepare('SELECT deleted_at FROM time_entries WHERE id = :id AND firma_id = :firma_id');
        $statement->execute(['id' => $entry['id'], 'firma_id' => $firmaId]);
        self::assertNotNull($statement->fetchColumn());
        self::assertSame([], $this->service->list($firmaId, []));
    }

    public function test_resumen_sums_correctly(): void
    {
        [$firmaId, $usuarioId, $casoId] = $this->createTenant('Firma Uno');
        $this->service->create($firmaId, $usuarioId, array_replace($this->validData($casoId), [
            'duracion_minutos' => 60,
            'tarifa_hora' => 120000,
        ]));
        $this->service->create($firmaId, $usuarioId, array_replace($this->validData($casoId), [
            'descripcion' => 'Actividad administrativa',
            'duracion_minutos' => 30,
            'es_facturable' => false,
        ]));

        $summary = $this->service->resumenByCaso($casoId, $firmaId);

        self::assertSame(90, $summary['total_minutos']);
        self::assertSame(1.5, $summary['total_horas']);
        self::assertSame(60, $summary['minutos_facturables']);
        self::assertSame(30, $summary['minutos_no_facturables']);
        self::assertSame(120000.0, $summary['monto_estimado']);
    }

    /** @return array<string, mixed> */
    private function validData(int $casoId): array
    {
        return [
            'caso_id' => $casoId,
            'descripcion' => 'Análisis jurídico del expediente',
            'fecha' => date('Y-m-d'),
            'duracion_minutos' => 45,
            'es_facturable' => true,
        ];
    }

    /** @return array{int, int, int} */
    private function createTenant(string $name): array
    {
        $statement = $this->pdo->prepare('INSERT INTO firmas (nombre) VALUES (:nombre)');
        $statement->execute(['nombre' => $name]);
        $firmaId = (int) $this->pdo->lastInsertId();

        $statement = $this->pdo->prepare(
            'INSERT INTO usuarios (firma_id, nombre, tarifa_hora) VALUES (:firma_id, :nombre, :tarifa_hora)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'nombre' => 'Abogado ' . $name,
            'tarifa_hora' => 250000,
        ]);
        $usuarioId = (int) $this->pdo->lastInsertId();

        $statement = $this->pdo->prepare(
            'INSERT INTO clientes (firma_id, nombre_razon_social) VALUES (:firma_id, :nombre)'
        );
        $statement->execute(['firma_id' => $firmaId, 'nombre' => 'Cliente ' . $name]);
        $clienteId = (int) $this->pdo->lastInsertId();

        $statement = $this->pdo->prepare(
            'INSERT INTO casos (firma_id, cliente_id, titulo) VALUES (:firma_id, :cliente_id, :titulo)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'cliente_id' => $clienteId,
            'titulo' => 'Caso ' . $name,
        ]);

        return [$firmaId, $usuarioId, (int) $this->pdo->lastInsertId()];
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE firmas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL
            );
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                nombre TEXT NOT NULL,
                tarifa_hora NUMERIC NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                nombre_razon_social TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE casos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                responsable_usuario_id INTEGER NULL,
                titulo TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE terminos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                caso_id INTEGER NULL,
                termino_id INTEGER NULL,
                responsable_usuario_id INTEGER NULL,
                titulo TEXT NOT NULL,
                deleted_at TEXT NULL
            );
            CREATE TABLE time_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                caso_id INTEGER NOT NULL,
                tarea_id INTEGER NULL,
                usuario_id INTEGER NOT NULL,
                descripcion TEXT NOT NULL,
                fecha TEXT NOT NULL,
                duracion_minutos INTEGER NOT NULL,
                tarifa_hora NUMERIC NULL,
                es_facturable INTEGER NOT NULL DEFAULT 1,
                facturado INTEGER NOT NULL DEFAULT 0,
                honorario_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT NULL
            );'
        );
    }
}
