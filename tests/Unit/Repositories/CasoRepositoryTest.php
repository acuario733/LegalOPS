<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\CasoRepository;

/**
 * Tests de CasoRepository.
 *
 * Los casos tienen múltiples transiciones de estado especiales (close, archive, reopen)
 * y pertenecen a clientes que a su vez pertenecen a una firma — doble FK a verificar.
 */
class CasoRepositoryTest extends RepositoryTestCase
{
    private CasoRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Columnas extra usadas por CasoRepository
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN numero_expediente TEXT");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN juzgado TEXT");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN area_practica TEXT");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN honorarios_acordados REAL");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN honorarios_pagados REAL DEFAULT 0");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN archivado_at TEXT");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN cerrado_at TEXT");
        $this->pdo->exec("ALTER TABLE casos ADD COLUMN razon_cierre TEXT");

        $this->repo = new CasoRepository($this->pdo);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function setupFirmaConCliente(): array
    {
        $firmaId   = $this->insertFirma();
        $clienteId = $this->insertCliente($firmaId);
        return [$firmaId, $clienteId];
    }

    private function makeData(int $firmaId, int $clienteId, string $sufijo = ''): array
    {
        return [
            'firma_id'    => $firmaId,
            'cliente_id'  => $clienteId,
            'titulo'      => "Caso Test $sufijo",
            'descripcion' => "Descripción del caso $sufijo",
            'estado'      => 'activo',
            'prioridad'   => 'media',
            'tipo'        => 'civil',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_paginate_returns_only_casos_of_given_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteA = $this->insertCliente($firmaA);
        $clienteB = $this->insertCliente($firmaB);

        $this->repo->create($this->makeData($firmaA, $clienteA, 'A1'));
        $this->repo->create($this->makeData($firmaA, $clienteA, 'A2'));
        $this->repo->create($this->makeData($firmaB, $clienteB, 'B1')); // no debe aparecer

        $resultado = $this->repo->paginate($firmaA, []);

        $this->assertCount(2, $resultado['items']);
        $this->assertSame(2, $resultado['total']);
    }

    public function test_find_for_firma_returns_caso(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'X'));

        $caso = $this->repo->findForFirma($firmaId, $id);

        $this->assertNotNull($caso);
        $this->assertSame($id, (int) $caso['id']);
        $this->assertSame($firmaId, (int) $caso['firma_id']);
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A no puede leer casos de Firma B.
     */
    public function test_find_returns_null_when_caso_belongs_to_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);

        $idEnFirmaB = $this->repo->create($this->makeData($firmaB, $clienteB, 'B'));

        $resultado = $this->repo->findForFirma($firmaA, $idEnFirmaB);

        $this->assertNull($resultado, 'SEGURIDAD: Firma A no puede leer casos de Firma B');
    }

    public function test_create_returns_valid_id(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();

        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'NEW'));

        $this->assertGreaterThan(0, $id);
    }

    public function test_update_modifies_titulo(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'UPD'));

        $data = $this->makeData($firmaId, $clienteId, 'MOD');
        $data['titulo'] = 'Título Actualizado del Caso';
        $this->repo->update($firmaId, $id, $data);

        $row = $this->pdo->query("SELECT titulo FROM casos WHERE id = $id")->fetch();
        $this->assertSame('Título Actualizado del Caso', $row['titulo']);
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A no puede modificar casos de Firma B.
     */
    public function test_update_does_not_affect_caso_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteA = $this->insertCliente($firmaA);
        $clienteB = $this->insertCliente($firmaB);

        $id = $this->repo->create($this->makeData($firmaB, $clienteB, 'B'));
        $tituloOriginal = 'Caso Test B';

        $data = $this->makeData($firmaA, $clienteA, 'HACK');
        $data['titulo'] = 'Hackeado';
        $this->repo->update($firmaA, $id, $data);

        $row = $this->pdo->query("SELECT titulo FROM casos WHERE id = $id")->fetch();
        $this->assertSame($tituloOriginal, $row['titulo'], 'SEGURIDAD: update no debe afectar casos de otras firmas');
    }

    public function test_close_sets_estado_and_cerrado_at(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'CL'));

        $this->repo->close($firmaId, $id, 'ganado');

        $row = $this->pdo->query("SELECT estado, cerrado_at FROM casos WHERE id = $id")->fetch();
        $this->assertSame('cerrado', $row['estado'], 'close() debe poner estado = cerrado');
        $this->assertNotNull($row['cerrado_at'], 'close() debe setear cerrado_at');
    }

    /**
     * SEGURIDAD CRÍTICA: close() no debe afectar casos de otra firma.
     */
    public function test_close_does_not_affect_caso_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);

        $id = $this->repo->create($this->makeData($firmaB, $clienteB, 'B'));

        $this->repo->close($firmaA, $id, 'perdido');

        $row = $this->pdo->query("SELECT estado FROM casos WHERE id = $id")->fetch();
        $this->assertSame('activo', $row['estado'], 'SEGURIDAD: close() no debe afectar casos de otras firmas');
    }

    public function test_archive_sets_estado_archivado(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'AR'));

        $this->repo->archive($firmaId, $id);

        $row = $this->pdo->query("SELECT estado FROM casos WHERE id = $id")->fetch();
        $this->assertSame('archivado', $row['estado']);
    }

    public function test_reopen_resets_estado_to_activo(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'RE'));

        $this->repo->close($firmaId, $id, 'ganado');
        $this->repo->reopen($firmaId, $id);

        $row = $this->pdo->query("SELECT estado, cerrado_at FROM casos WHERE id = $id")->fetch();
        $this->assertSame('activo', $row['estado'], 'reopen() debe restaurar estado a activo');
        $this->assertNull($row['cerrado_at'], 'reopen() debe limpiar cerrado_at');
    }

    public function test_soft_delete_prevents_caso_from_appearing_in_paginate(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id1 = $this->repo->create($this->makeData($firmaId, $clienteId, '1'));
        $id2 = $this->repo->create($this->makeData($firmaId, $clienteId, '2'));

        $this->repo->softDelete($firmaId, $id2);

        $resultado = $this->repo->paginate($firmaId, []);
        $this->assertCount(1, $resultado['items']);
    }

    /**
     * SEGURIDAD CRÍTICA: softDelete no debe eliminar casos de otra firma.
     */
    public function test_soft_delete_does_not_delete_caso_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);

        $id = $this->repo->create($this->makeData($firmaB, $clienteB, 'B'));

        $this->repo->softDelete($firmaA, $id);

        $row = $this->pdo->query("SELECT deleted_at FROM casos WHERE id = $id")->fetch();
        $this->assertNull($row['deleted_at'], 'SEGURIDAD: softDelete no debe afectar casos de otras firmas');
    }
}
