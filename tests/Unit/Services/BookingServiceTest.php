<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\BookingRepository;
use App\Repositories\ProspectoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\BookingService;
use App\Services\LocalMailService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class BookingServiceTest extends TestCase
{
    private PDO $pdo;
    private BookingService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->service = new BookingService(
            new BookingRepository($this->pdo),
            new ProspectoRepository($this->pdo),
            new UsuarioRepository($this->pdo),
            $this->database($this->pdo),
            new LocalMailService()
        );
    }

    public function test_available_slots_excludes_occupied(): void
    {
        [$firmaId, $userId] = $this->createTenant('Firma A', 'firma-a');
        $config = $this->service->createConfig($firmaId, $userId, $this->configData());
        $date = $this->nextDateForDays([1,2,3,4,5,6,7]);
        $this->insertAppointment($config, $date, '09:00:00');

        $slots = $this->service->getAvailableSlots((string) $config['slug'], $date);

        self::assertNotContains('09:00', $slots);
        self::assertContains('09:30', $slots);
    }

    public function test_available_slots_respects_business_hours(): void
    {
        [$firmaId, $userId] = $this->createTenant('Firma A', 'firma-a');
        $config = $this->service->createConfig($firmaId, $userId, $this->configData());

        $slots = $this->service->getAvailableSlots(
            (string) $config['slug'],
            $this->nextDateForDays([1,2,3,4,5,6,7])
        );

        self::assertSame(['09:00', '09:30', '10:00', '10:30'], $slots);
    }

    public function test_available_slots_excludes_inactive_days(): void
    {
        [$firmaId, $userId] = $this->createTenant('Firma A', 'firma-a');
        $config = $this->service->createConfig(
            $firmaId,
            $userId,
            array_replace($this->configData(), ['dias_activos' => [1]])
        );

        $slots = $this->service->getAvailableSlots(
            (string) $config['slug'],
            $this->nextDateForDays([2,3,4,5,6,7])
        );

        self::assertSame([], $slots);
    }

    public function test_create_appointment_creates_prospecto(): void
    {
        [$firmaId, $userId] = $this->createTenant('Firma A', 'firma-a');
        $config = $this->service->createConfig($firmaId, $userId, $this->configData());
        $date = $this->nextDateForDays([1,2,3,4,5,6,7]);

        $appointment = $this->service->createAppointment((string) $config['slug'], [
            'nombre_cliente' => 'Laura Gómez',
            'email_cliente' => 'laura@example.com',
            'telefono_cliente' => '3005550011',
            'fecha' => $date,
            'hora_inicio' => '09:00',
            'notas' => 'Consulta laboral',
        ]);

        $prospect = $this->pdo->query('SELECT * FROM prospectos')->fetch();
        self::assertSame('booking', $prospect['fuente']);
        self::assertSame((int) $prospect['id'], (int) $appointment['prospecto_id']);
    }

    public function test_cancel_by_token_changes_estado(): void
    {
        [$firmaId, $userId] = $this->createTenant('Firma A', 'firma-a');
        $config = $this->service->createConfig($firmaId, $userId, $this->configData());
        $appointment = $this->service->createAppointment((string) $config['slug'], [
            'nombre_cliente' => 'Laura Gómez',
            'email_cliente' => 'laura@example.com',
            'fecha' => $this->nextDateForDays([1,2,3,4,5,6,7]),
            'hora_inicio' => '09:00',
        ]);

        $this->service->cancelByToken((string) $appointment['token_cancelacion']);

        self::assertSame('cancelada', $this->service->appointmentByToken((string) $appointment['token_cancelacion'])['estado']);
    }

    public function test_cannot_book_slot_in_the_past(): void
    {
        [$firmaId, $userId] = $this->createTenant('Firma A', 'firma-a');
        $config = $this->service->createConfig($firmaId, $userId, $this->configData());

        $this->expectException(HttpException::class);
        $this->service->getAvailableSlots(
            (string) $config['slug'],
            (new DateTimeImmutable('yesterday'))->format('Y-m-d')
        );
    }

    public function test_tenant_isolation_config_belongs_to_firma(): void
    {
        [$firmaA, $userA] = $this->createTenant('Firma A', 'firma-a');
        [$firmaB] = $this->createTenant('Firma B', 'firma-b');
        $config = $this->service->createConfig($firmaA, $userA, $this->configData());

        self::assertNull($this->service->configForUser($userA, $firmaB));
        $this->expectException(HttpException::class);
        $this->service->updateConfig((int) $config['id'], $firmaB, $this->configData());
    }

    /** @return array<string, mixed> */
    private function configData(): array
    {
        return [
            'titulo' => 'Consulta legal',
            'descripcion' => 'Agenda una consulta.',
            'duracion_minutos' => 30,
            'dias_activos' => [1,2,3,4,5,6,7],
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'buffer_entre_citas' => 0,
            'dias_anticipacion_min' => 0,
            'dias_anticipacion_max' => 30,
            'activo' => true,
            'notificar_email' => null,
        ];
    }

    /** @return array{int, int} */
    private function createTenant(string $name, string $slug): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO firmas (nombre, slug, estado, timezone) VALUES (:nombre, :slug, \'activa\', \'UTC\')'
        );
        $statement->execute(['nombre' => $name, 'slug' => $slug]);
        $firmaId = (int) $this->pdo->lastInsertId();
        $statement = $this->pdo->prepare(
            'INSERT INTO usuarios (firma_id, nombre, email) VALUES (:firma_id, :nombre, :email)'
        );
        $statement->execute([
            'firma_id' => $firmaId,
            'nombre' => 'Abogado ' . $name,
            'email' => strtolower(str_replace(' ', '', $slug)) . '@example.com',
        ]);

        return [$firmaId, (int) $this->pdo->lastInsertId()];
    }

    /** @param array<string, mixed> $config */
    private function insertAppointment(array $config, string $date, string $time): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO booking_appointments
                (firma_id,booking_config_id,usuario_id,nombre_cliente,email_cliente,fecha,
                 hora_inicio,hora_fin,estado,token_cancelacion)
             VALUES (:firma_id,:config_id,:usuario_id,\'Ocupado\',\'ocupado@example.com\',:fecha,
                     :hora_inicio,\'09:30:00\',\'confirmada\',:token)'
        );
        $statement->execute([
            'firma_id' => $config['firma_id'],
            'config_id' => $config['id'],
            'usuario_id' => $config['usuario_id'],
            'fecha' => $date,
            'hora_inicio' => $time,
            'token' => bin2hex(random_bytes(32)),
        ]);
    }

    /** @param list<int> $days */
    private function nextDateForDays(array $days): string
    {
        $date = new DateTimeImmutable('tomorrow');
        while (!in_array((int) $date->format('N'), $days, true)) {
            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d');
    }

    private function database(PDO $pdo): Database
    {
        $database = new Database(['driver' => 'mysql']);
        $property = new ReflectionProperty(Database::class, 'connection');
        $property->setValue($database, $pdo);

        return $database;
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE firmas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,nombre TEXT,slug TEXT UNIQUE,
                estado TEXT,timezone TEXT,deleted_at TEXT
            );
            CREATE TABLE usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre TEXT,email TEXT,
                deleted_at TEXT
            );
            CREATE TABLE prospectos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,nombre TEXT,
                nombre_normalizado TEXT,tipo_persona TEXT,email TEXT,telefono TEXT,fuente TEXT,
                fuente_referencia TEXT,estado TEXT,notas TEXT,tratamiento_datos_autorizado INTEGER,
                deleted_at TEXT,created_at TEXT,updated_at TEXT
            );
            CREATE TABLE booking_configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,usuario_id INTEGER,slug TEXT UNIQUE,
                titulo TEXT,descripcion TEXT,duracion_minutos INTEGER,dias_activos TEXT,hora_inicio TEXT,
                hora_fin TEXT,buffer_entre_citas INTEGER,dias_anticipacion_min INTEGER,
                dias_anticipacion_max INTEGER,activo INTEGER,notificar_email TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT
            );
            CREATE TABLE booking_appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,firma_id INTEGER,booking_config_id INTEGER,
                usuario_id INTEGER,nombre_cliente TEXT,email_cliente TEXT,telefono_cliente TEXT,
                fecha TEXT,hora_inicio TEXT,hora_fin TEXT,notas TEXT,estado TEXT,prospecto_id INTEGER,
                token_cancelacion TEXT UNIQUE,created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );'
        );
    }
}
