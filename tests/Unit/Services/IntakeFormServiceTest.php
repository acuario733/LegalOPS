<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\IntakeFormRepository;
use App\Repositories\ProspectoRepository;
use App\Services\IntakeFormService;
use App\Services\LocalMailService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IntakeFormServiceTest extends TestCase
{
    private PDO $pdo;
    private IntakeFormService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->service = new IntakeFormService(
            new IntakeFormRepository($this->pdo),
            new ProspectoRepository($this->pdo),
            $this->database($this->pdo),
            new LocalMailService()
        );
    }

    public function test_create_form_generates_unique_slug(): void
    {
        $firmaId = $this->insertFirma('Firma A', 'firma-a');

        $first = $this->service->create($firmaId, $this->formData('Consulta Inicial'));
        $second = $this->service->create($firmaId, $this->formData('Consulta Inicial'));

        self::assertSame('consulta-inicial', $first['slug']);
        self::assertNotSame($first['slug'], $second['slug']);
    }

    public function test_process_submission_creates_prospecto(): void
    {
        $firmaId = $this->insertFirma('Firma A', 'firma-a');
        $form = $this->service->create($firmaId, $this->formData());

        $result = $this->service->processSubmission((int) $form['id'], [
            'nombre' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'telefono' => '3001234567',
        ], '127.0.0.1', 'PHPUnit');

        $prospect = $this->pdo->query('SELECT * FROM prospectos')->fetch();
        self::assertSame((int) $prospect['id'], $result['prospecto_id']);
        self::assertSame($firmaId, (int) $prospect['firma_id']);
        self::assertSame('intake_form', $prospect['fuente']);
    }

    public function test_process_submission_rejects_inactive_form(): void
    {
        $firmaId = $this->insertFirma('Firma A', 'firma-a');
        $form = $this->service->create($firmaId, $this->formData());
        $this->pdo->exec('UPDATE intake_forms SET activo = 0 WHERE id = ' . (int) $form['id']);

        $this->expectException(HttpException::class);
        $this->service->processSubmission((int) $form['id'], [
            'nombre' => 'Ana Pérez',
            'email' => 'ana@example.com',
        ], '127.0.0.1', 'PHPUnit');
    }

    public function test_process_submission_validates_required_fields(): void
    {
        $firmaId = $this->insertFirma('Firma A', 'firma-a');
        $form = $this->service->create($firmaId, $this->formData());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Complete el campo obligatorio');
        $this->service->processSubmission((int) $form['id'], [
            'nombre' => 'Ana Pérez',
        ], '127.0.0.1', 'PHPUnit');
    }

    public function test_tenant_isolation_cannot_see_other_firma_forms(): void
    {
        $firmaA = $this->insertFirma('Firma A', 'firma-a');
        $firmaB = $this->insertFirma('Firma B', 'firma-b');
        $this->service->create($firmaA, $this->formData('Formulario A'));
        $this->service->create($firmaB, $this->formData('Formulario B'));

        $forms = $this->service->list($firmaA);

        self::assertCount(1, $forms);
        self::assertSame($firmaA, (int) $forms[0]['firma_id']);
    }

    public function test_submission_marks_as_processed_after_prospecto_created(): void
    {
        $firmaId = $this->insertFirma('Firma A', 'firma-a');
        $form = $this->service->create($firmaId, $this->formData());
        $this->service->processSubmission((int) $form['id'], [
            'nombre' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'telefono' => '',
        ], '127.0.0.1', 'PHPUnit');

        $submission = $this->pdo->query('SELECT * FROM intake_submissions')->fetch();
        self::assertSame(1, (int) $submission['procesado']);
        self::assertGreaterThan(0, (int) $submission['prospecto_id']);
    }

    /** @return array<string, mixed> */
    private function formData(string $name = 'Formulario Web'): array
    {
        return [
            'nombre' => $name,
            'titulo' => 'Cuéntanos cómo podemos ayudarte',
            'descripcion' => 'Completa tus datos.',
            'campos' => [
                ['key' => 'nombre', 'type' => 'texto', 'etiqueta' => 'Nombre', 'requerido' => true, 'especial' => 'nombre', 'fijo' => true],
                ['key' => 'email', 'type' => 'email', 'etiqueta' => 'Email', 'requerido' => true, 'especial' => 'email', 'fijo' => true],
                ['key' => 'telefono', 'type' => 'telefono', 'etiqueta' => 'Teléfono', 'requerido' => false, 'especial' => 'telefono', 'fijo' => true],
            ],
            'activo' => true,
        ];
    }

    private function insertFirma(string $name, string $slug): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO firmas (nombre, slug, estado, timezone) VALUES (:nombre, :slug, \'activa\', \'UTC\')'
        );
        $statement->execute(['nombre' => $name, 'slug' => $slug]);

        return (int) $this->pdo->lastInsertId();
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
                id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, slug TEXT UNIQUE,
                estado TEXT, timezone TEXT, deleted_at TEXT
            );
            CREATE TABLE prospectos (
                id INTEGER PRIMARY KEY AUTOINCREMENT, firma_id INTEGER NOT NULL,
                nombre TEXT, nombre_normalizado TEXT, tipo_persona TEXT, email TEXT,
                telefono TEXT, fuente TEXT, fuente_referencia TEXT, estado TEXT,
                notas TEXT, tratamiento_datos_autorizado INTEGER, deleted_at TEXT,
                created_at TEXT, updated_at TEXT
            );
            CREATE TABLE intake_forms (
                id INTEGER PRIMARY KEY AUTOINCREMENT, firma_id INTEGER NOT NULL,
                nombre TEXT, slug TEXT UNIQUE, titulo TEXT, descripcion TEXT, campos TEXT,
                activo INTEGER, mensaje_exito TEXT, notificar_emails TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                deleted_at TEXT
            );
            CREATE TABLE intake_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT, intake_form_id INTEGER, firma_id INTEGER,
                datos TEXT, prospecto_id INTEGER, ip_address TEXT, user_agent TEXT,
                procesado INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );'
        );
    }
}
