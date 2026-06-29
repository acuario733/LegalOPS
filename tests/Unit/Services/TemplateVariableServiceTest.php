<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FirmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\TemplateVariableService;
use PDO;
use PHPUnit\Framework\TestCase;

final class TemplateVariableServiceTest extends TestCase
{
    private TemplateVariableService $service;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->service = new TemplateVariableService(
            new CasoRepository($pdo),
            new ClienteRepository($pdo),
            new FirmaRepository($pdo),
            new UsuarioRepository($pdo)
        );
    }

    public function test_resolves_caso_variable(): void
    {
        self::assertSame('Caso Ejecutivo', $this->service->resolve('{{caso.titulo}}', ['caso' => ['titulo' => 'Caso Ejecutivo']]));
    }

    public function test_resolves_cliente_variable(): void
    {
        self::assertSame('Ana Perez', $this->service->resolve('{{cliente.nombre}}', ['cliente' => ['nombre' => 'Ana Perez']]));
    }

    public function test_resolves_fecha_hoy(): void
    {
        self::assertSame(date('d/m/Y'), $this->service->resolve('{{fecha.hoy}}', ['fecha' => ['hoy' => date('d/m/Y')]]));
    }

    public function test_leaves_unknown_variable_unchanged(): void
    {
        self::assertSame('{{caso.no_existe}}', $this->service->resolve('{{caso.no_existe}}', ['caso' => ['titulo' => 'X']]));
    }

    public function test_escapes_html_in_variable_value(): void
    {
        self::assertSame('&lt;script&gt;', $this->service->resolve('{{cliente.nombre}}', ['cliente' => ['nombre' => '<script>']]));
    }

    public function test_extracts_variables_from_html(): void
    {
        self::assertSame(['caso.titulo', 'cliente.nombre'], $this->service->extractFromContent('<p>{{caso.titulo}} {{ cliente.nombre }}</p>{{caso.titulo}}'));
    }

    public function test_custom_variables_resolved(): void
    {
        self::assertSame('Valor libre', $this->service->resolve('{{custom.campo_1}}', ['custom' => ['campo_1' => 'Valor libre']]));
    }

    public function test_resolve_with_empty_context_leaves_all_unchanged(): void
    {
        self::assertSame('{{caso.titulo}} {{cliente.nombre}}', $this->service->resolve('{{caso.titulo}} {{cliente.nombre}}', []));
    }
}
