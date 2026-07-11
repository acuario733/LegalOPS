<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\HttpException;
use App\Services\DocumentoVersionService;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Verifica la validación de magic bytes (F6-4) de DocumentoVersionService.
 *
 * Crea archivos temporales reales con bytes controlados para ejercitar cada rama
 * del método verifyMagicBytes(). Los criterios del plan original son:
 *   - Un .php renombrado a .pdf se rechaza.
 *   - Un PDF real con extensión .doc se rechaza.
 *   - Los tipos soportados (pdf, docx, xlsx, png, jpg, txt) se aceptan sin cambios.
 */
#[Group('phase3c')]
class DocumentoVersionMagicBytesTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    // ── helper: crea archivo temporal con contenido controlado ───────────────

    private function tmp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'magic_test_');
        if ($path === false) {
            $this->fail('No se pudo crear archivo temporal.');
        }
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function service(): DocumentoVersionService
    {
        // Construimos el servicio con dependencias mínimas; verifyMagicBytes
        // solo necesita el archivo temporal y la extensión, sin acceso a DB.
        $pdo = new PDO('sqlite::memory:');

        return new DocumentoVersionService(
            repository: new \App\Repositories\DocumentoVersionRepository($pdo),
            documentos: new \App\Repositories\DocumentoRepository($pdo),
            validator:  new \App\Validators\DocumentoVersionValidator(),
            database:   new \App\Core\Database([]),
            audit:      new \App\Services\AuditoriaService(
                new \App\Repositories\AuditoriaRepository($pdo),
                new \App\Core\Auth(new \App\Core\Session(['name' => 'magic_test', 'secure' => false]))
            ),
            auth:       new \App\Core\Auth(new \App\Core\Session(['name' => 'magic_test2', 'secure' => false])),
        );
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    public function test_pdf_valido_es_aceptado(): void
    {
        $path = $this->tmp('%PDF-1.4 fake pdf content');
        $this->service()->verifyMagicBytes($path, 'pdf');
        $this->assertTrue(true); // sin excepcion = aceptado
    }

    public function test_php_renombrado_a_pdf_es_rechazado(): void
    {
        $path = $this->tmp("<?php echo 'hack'; ?>");

        try {
            $this->service()->verifyMagicBytes($path, 'pdf');
            $this->fail('Se esperaba HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status());
        }
    }

    // ── Criterio clave del plan: PDF real con extension .doc se rechaza ──────

    public function test_pdf_con_extension_doc_es_rechazado(): void
    {
        // Bytes de PDF (%PDF) presentados como si fueran un .doc (OLE2)
        $path = $this->tmp('%PDF-1.4 content pretending to be a Word doc');

        try {
            $this->service()->verifyMagicBytes($path, 'doc');
            $this->fail('Se esperaba HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status());
        }
    }

    // ── PNG ──────────────────────────────────────────────────────────────────

    public function test_png_valido_es_aceptado(): void
    {
        $path = $this->tmp("\x89PNG\r\n\x1a\nfake png body");
        $this->service()->verifyMagicBytes($path, 'png');
        $this->assertTrue(true);
    }

    public function test_pdf_renombrado_a_png_es_rechazado(): void
    {
        $path = $this->tmp('%PDF-1.4 content');

        try {
            $this->service()->verifyMagicBytes($path, 'png');
            $this->fail('Se esperaba HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status());
        }
    }

    // ── JPEG ─────────────────────────────────────────────────────────────────

    public function test_jpeg_valido_es_aceptado(): void
    {
        $path = $this->tmp("\xff\xd8\xff\xe0fake jpeg");
        $this->service()->verifyMagicBytes($path, 'jpeg');
        $this->assertTrue(true);
    }

    public function test_jpg_alias_es_aceptado(): void
    {
        $path = $this->tmp("\xff\xd8\xff\xe0fake jpeg");
        $this->service()->verifyMagicBytes($path, 'jpg');
        $this->assertTrue(true);
    }

    // ── DOC / XLS (OLE2) ─────────────────────────────────────────────────────

    public function test_doc_ole2_valido_es_aceptado(): void
    {
        $path = $this->tmp("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1fake doc");
        $this->service()->verifyMagicBytes($path, 'doc');
        $this->assertTrue(true);
    }

    public function test_xls_ole2_valido_es_aceptado(): void
    {
        $path = $this->tmp("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1fake xls");
        $this->service()->verifyMagicBytes($path, 'xls');
        $this->assertTrue(true);
    }

    // ── ZIP genérico con extension .docx rechazado (sin estructura OOXML) ────

    #[RequiresPhpExtension('zip')]
    public function test_zip_generico_como_docx_es_rechazado(): void
    {
        // PK\x03\x04 = ZIP magic, pero sin word/document.xml ni [Content_Types].xml
        // Un ZIP real vacío (o con archivos distintos) no es un DOCX válido.
        // Creamos un ZIP mínimo que solo tenga un archivo no relacionado.
        $zipPath = sys_get_temp_dir() . '/magic_test_generic_' . uniqid() . '.zip';
        $this->tempFiles[] = $zipPath;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('random_file.txt', 'not an ooxml package');
        $zip->close();

        try {
            $this->service()->verifyMagicBytes($zipPath, 'docx');
            $this->fail('Se esperaba HttpException para ZIP sin estructura OOXML.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status());
        }
    }

    // ── DOCX real (ZIP con estructura OOXML) es aceptado ────────────────────

    #[RequiresPhpExtension('zip')]
    public function test_docx_con_estructura_ooxml_es_aceptado(): void
    {
        $zipPath = sys_get_temp_dir() . '/magic_test_docx_' . uniqid() . '.docx';
        $this->tempFiles[] = $zipPath;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document/>');
        $zip->close();

        $this->service()->verifyMagicBytes($zipPath, 'docx');
        $this->assertTrue(true);
    }

    // ── XLSX real (ZIP con xl/workbook.xml) es aceptado ──────────────────────

    #[RequiresPhpExtension('zip')]
    public function test_xlsx_con_estructura_ooxml_es_aceptado(): void
    {
        $zipPath = sys_get_temp_dir() . '/magic_test_xlsx_' . uniqid() . '.xlsx';
        $this->tempFiles[] = $zipPath;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook/>');
        $zip->close();

        $this->service()->verifyMagicBytes($zipPath, 'xlsx');
        $this->assertTrue(true);
    }

    // ── TXT: sin magic bytes, siempre aceptado ───────────────────────────────

    public function test_txt_cualquier_contenido_es_aceptado(): void
    {
        $path = $this->tmp("Contenido de texto plano sin magic bytes.");
        $this->service()->verifyMagicBytes($path, 'txt');
        $this->assertTrue(true);
    }
}
