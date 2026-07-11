<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\CasoRepository;

/**
 * Tests de CasoRepository.
 *
 * Los casos tienen múltiples transiciones de estado especiales (close, archive, reopen)
 * y pertenecen a clientes que a su vez pertenecen a una firma — doble FK a verificar.
 *
 * Reescrito el 2026-07-11: el archivo anterior probaba una API vieja de CasoRepository
 * (columnas y firmas de método que ya no existen — ver docs/IMPLEMENTACION_FASES.md).
 * Este archivo se verificó columna por columna contra database/migrations/0203_create_casos.sql,
 * 0514_phase2_caso_numero.sql y 0513_phase2_caso_etapas.sql, y método por método contra
 * app/Repositories/CasoRepository.php.
 */
class CasoRepositoryTest extends RepositoryTestCase
{
    private CasoRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Columnas reales de "casos" que no están en el esquema base compartido
        // (el esquema base de RepositoryTestCase quedó con nombres antiguos:
        // abogado_id, numero_caso, fecha_inicio — se dejan sin usar, no rompen nada).
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN numero TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN responsable_usuario_id INTEGER');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN titulo_normalizado TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN tipo_proceso TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN jurisdiccion TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN despacho TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN radicado TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN fecha_apertura TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN closed_at TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN closed_by_usuario_id INTEGER');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN close_reason TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN archived_at TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN archived_by_usuario_id INTEGER');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN archive_reason TEXT');
        $this->pdo->exec('ALTER TABLE casos ADD COLUMN etapa_actual_id INTEGER');

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

    /** @return array<string, mixed> */
    private function makeData(int $firmaId, int $clienteId, string $sufijo = ''): array
    {
        return [
            'firma_id'               => $firmaId,
            'numero'                 => "2026-$sufijo",
            'cliente_id'             => $clienteId,
            'responsable_usuario_id' => null,
            'titulo'                 => "Caso Test $sufijo",
            'titulo_normalizado'     => strtolower("caso test $sufijo"),
            'descripcion'            => "Descripción del caso $sufijo",
            'estado'                 => 'activo',
            'prioridad'              => 'media',
            'tipo_proceso'           => 'civil',
            'jurisdiccion'           => 'Bogotá',
            'despacho'               => 'Juzgado 1 Civil',
            'radicado'               => "RAD-$sufijo",
            'fecha_apertura'         => date('Y-m-d'),
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
        unset($data['firma_id'], $data['numero']);
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
        unset($data['firma_id'], $data['numero']);
        $data['titulo'] = 'Hackeado';
        $this->repo->update($firmaA, $id, $data);

        $row = $this->pdo->query("SELECT titulo FROM casos WHERE id = $id")->fetch();
        $this->assertSame($tituloOriginal, $row['titulo'], 'SEGURIDAD: update no debe afectar casos de otras firmas');
    }

    public function test_close_sets_estado_and_closed_at(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $userId = $this->insertUser($firmaId);
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'CL'));

        $this->repo->close($firmaId, $id, $userId, 'ganado');

        $row = $this->pdo->query("SELECT estado, closed_at, close_reason FROM casos WHERE id = $id")->fetch();
        $this->assertSame('cerrado', $row['estado'], 'close() debe poner estado = cerrado');
        $this->assertNotNull($row['closed_at'], 'close() debe setear closed_at');
        $this->assertSame('ganado', $row['close_reason']);
    }

    /**
     * SEGURIDAD CRÍTICA: close() no debe afectar casos de otra firma.
     */
    public function test_close_does_not_affect_caso_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);
        $userA    = $this->insertUser($firmaA, 'userA@test.mx');

        $id = $this->repo->create($this->makeData($firmaB, $clienteB, 'B'));

        $this->repo->close($firmaA, $id, $userA, 'perdido');

        $row = $this->pdo->query("SELECT estado FROM casos WHERE id = $id")->fetch();
        $this->assertSame('activo', $row['estado'], 'SEGURIDAD: close() no debe afectar casos de otras firmas');
    }

    public function test_archive_sets_estado_archivado(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $userId = $this->insertUser($firmaId);
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'AR'));

        $this->repo->archive($firmaId, $id, $userId, 'sin actividad');

        $row = $this->pdo->query("SELECT estado, archived_at FROM casos WHERE id = $id")->fetch();
        $this->assertSame('archivado', $row['estado']);
        $this->assertNotNull($row['archived_at']);
    }

    public function test_reopen_resets_estado_to_activo(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $userId = $this->insertUser($firmaId);
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'RE'));

        $this->repo->close($firmaId, $id, $userId, 'ganado');
        $this->repo->reopen($firmaId, $id);

        $row = $this->pdo->query("SELECT estado FROM casos WHERE id = $id")->fetch();
        $this->assertSame('activo', $row['estado'], 'reopen() debe restaurar estado a activo');
    }

    public function test_set_current_stage_updates_etapa_actual_id(): void
    {
        [$firmaId, $clienteId] = $this->setupFirmaConCliente();
        $id = $this->repo->create($this->makeData($firmaId, $clienteId, 'ET'));

        $this->repo->setCurrentStage($firmaId, $id, 7);

        $row = $this->pdo->query("SELECT etapa_actual_id FROM casos WHERE id = $id")->fetch();
        $this->assertSame(7, (int) $row['etapa_actual_id']);
    }
}
