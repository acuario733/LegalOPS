<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Request;
use App\Repositories\UsuarioCambiosSensiblesRepository;
use App\Services\SensitiveDataService;
use App\Services\UsuarioCambiosSensiblesService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Cubre el servicio centralizado creado en la Sesion 8 de "Mi perfil" para
 * registrar y leer el historial de cambios sensibles/administrativos. Antes de
 * esta sesion, la logica de enmascaramiento vivia duplicada y sin pruebas
 * dentro de PerfilService::recordSensitiveChange.
 */
final class UsuarioCambiosSensiblesServiceTest extends TestCase
{
    private PDO $pdo;
    private UsuarioCambiosSensiblesService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec(
            'CREATE TABLE usuario_cambios_sensibles (
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
            )'
        );
        $this->service = new UsuarioCambiosSensiblesService(
            new UsuarioCambiosSensiblesRepository($this->pdo),
            new SensitiveDataService()
        );
    }

    public function test_documento_field_is_masked_and_hashed(): void
    {
        $this->service->registrar(1, 10, 5, 'numero_documento', '1000123456', '1000999999', 'mi_perfil', $this->request());

        $row = $this->pdo->query('SELECT * FROM usuario_cambios_sensibles')->fetch();
        self::assertNotFalse($row);
        self::assertStringContainsString('*', (string) $row['valor_anterior_enmascarado']);
        self::assertStringContainsString('*', (string) $row['valor_nuevo_enmascarado']);
        self::assertNotSame('1000123456', $row['valor_anterior_enmascarado']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $row['valor_anterior_hash']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $row['valor_nuevo_hash']);
    }

    public function test_non_sensitive_field_is_stored_in_plain_text_without_hash(): void
    {
        $this->service->registrar(1, 10, 5, 'cargo', 'Asistente', 'Abogado Senior', 'usuarios', $this->request());

        $row = $this->pdo->query('SELECT * FROM usuario_cambios_sensibles')->fetch();
        self::assertSame('Asistente', $row['valor_anterior_enmascarado']);
        self::assertSame('Abogado Senior', $row['valor_nuevo_enmascarado']);
        self::assertNull($row['valor_anterior_hash']);
        self::assertNull($row['valor_nuevo_hash']);
    }

    public function test_no_change_does_not_write_a_row(): void
    {
        $this->service->registrar(1, 10, 5, 'cargo', 'Abogado Senior', 'Abogado Senior', 'usuarios', $this->request());

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario_cambios_sensibles')->fetchColumn());
    }

    public function test_sin_firma_no_registra_nada(): void
    {
        $this->service->registrar(null, 10, 5, 'cargo', 'A', 'B', 'usuarios', $this->request());

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario_cambios_sensibles')->fetchColumn());
    }

    public function test_historial_returns_items_and_total(): void
    {
        $this->service->registrar(1, 10, 5, 'cargo', 'A', 'B', 'usuarios', $this->request());
        $this->service->registrar(1, 10, 5, 'cargo', 'B', 'C', 'usuarios', $this->request());
        $this->service->registrar(1, 99, 5, 'cargo', 'X', 'Y', 'usuarios', $this->request());

        $historial = $this->service->historial(1, 10);

        self::assertSame(2, $historial['total']);
        self::assertCount(2, $historial['items']);
    }

    private function request(): Request
    {
        return new Request(
            method: 'POST',
            uri: '/test',
            headers: ['user-agent' => 'PHPUnit'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );
    }
}
