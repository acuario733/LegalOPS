<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\TareaRepository;

/**
 * Tests de TareaRepository.
 *
 * Las tareas tienen estado, reasignación, fecha de término y están anidadas
 * dentro de un Caso que a su vez pertenece a una Firma (triple FK).
 */
class TareaRepositoryTest extends RepositoryTestCase
{
    private TareaRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Columnas extra de la tabla tareas
        $this->pdo->exec("ALTER TABLE tareas ADD COLUMN notas TEXT");
        $this->pdo->exec("ALTER TABLE tareas ADD COLUMN termino_real TEXT");
        $this->pdo->exec("ALTER TABLE tareas ADD COLUMN recurrente INTEGER DEFAULT 0");
        $this->pdo->exec("ALTER TABLE tareas ADD COLUMN recordatorio_at TEXT");

        $this->repo = new TareaRepository($this->pdo);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function setupFirmaConCaso(): array
    {
        $firmaId   = $this->insertFirma();
        $clienteId = $this->insertCliente($firmaId);
        $casoId    = $this->insertCaso($firmaId, $clienteId);
        return [$firmaId, $casoId];
    }

    private function makeData(int $firmaId, int $casoId, string $sufijo = ''): array
    {
        return [
            'firma_id'          => $firmaId,
            'caso_id'           => $casoId,
            'titulo'            => "Tarea Test $sufijo",
            'descripcion'       => "Desc tarea $sufijo",
            'estado'            => 'pendiente',
            'prioridad'         => 'media',
            'fecha_vencimiento' => date('Y-m-d', strtotime('+7 days')),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_paginate_returns_only_tareas_of_given_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteA = $this->insertCliente($firmaA);
        $clienteB = $this->insertCliente($firmaB);
        $casoA    = $this->insertCaso($firmaA, $clienteA);
        $casoB    = $this->insertCaso($firmaB, $clienteB);

        $this->repo->create($this->makeData($firmaA, $casoA, 'A1'));
        $this->repo->create($this->makeData($firmaA, $casoA, 'A2'));
        $this->repo->create($this->makeData($firmaB, $casoB, 'B1')); // no debe aparecer

        $resultado = $this->repo->paginate($firmaA, []);

        $this->assertCount(2, $resultado['items']);
        foreach ($resultado['items'] as $item) {
            $this->assertSame($firmaA, (int) $item['firma_id']);
        }
    }

    public function test_find_for_firma_returns_tarea(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'X'));

        $tarea = $this->repo->findForFirma($firmaId, $id);

        $this->assertNotNull($tarea);
        $this->assertSame($id, (int) $tarea['id']);
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A no puede leer tareas de Firma B.
     */
    public function test_find_returns_null_when_tarea_belongs_to_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);
        $casoB    = $this->insertCaso($firmaB, $clienteB);

        $idEnFirmaB = $this->repo->create($this->makeData($firmaB, $casoB, 'B'));

        $resultado = $this->repo->findForFirma($firmaA, $idEnFirmaB);

        $this->assertNull($resultado, 'SEGURIDAD: Firma A no puede leer tareas de Firma B');
    }

    public function test_create_returns_valid_id(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();

        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'NEW'));

        $this->assertGreaterThan(0, $id);
    }

    public function test_change_status_updates_estado(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'ST'));

        $this->repo->changeStatus($firmaId, $id, 'en_progreso');

        $row = $this->pdo->query("SELECT estado FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('en_progreso', $row['estado']);
    }

    public function test_change_status_to_completada_sets_completada_at(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'COMP'));

        $this->repo->changeStatus($firmaId, $id, 'completada');

        $row = $this->pdo->query("SELECT estado, completada_at FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('completada', $row['estado']);
        $this->assertNotNull($row['completada_at'], 'Al completar debe setearse completada_at');
    }

    /**
     * SEGURIDAD CRÍTICA: changeStatus no debe afectar tareas de otra firma.
     */
    public function test_change_status_does_not_affect_tarea_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);
        $casoB    = $this->insertCaso($firmaB, $clienteB);

        $id = $this->repo->create($this->makeData($firmaB, $casoB, 'B'));

        $this->repo->changeStatus($firmaA, $id, 'completada');

        $row = $this->pdo->query("SELECT estado FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('pendiente', $row['estado'], 'SEGURIDAD: changeStatus no debe afectar tareas de otras firmas');
    }

    public function test_reassign_changes_asignado_a(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id        = $this->repo->create($this->makeData($firmaId, $casoId, 'RA'));
        $userId    = $this->insertUser($firmaId, 'abogado@test.mx');

        $this->repo->reassign($firmaId, $id, $userId);

        $row = $this->pdo->query("SELECT asignado_a FROM tareas WHERE id = $id")->fetch();
        $this->assertSame($userId, (int) $row['asignado_a']);
    }

    /**
     * SEGURIDAD CRÍTICA: reassign no puede asignar a usuario de otra firma.
     */
    public function test_reassign_does_not_affect_tarea_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);
        $casoB    = $this->insertCaso($firmaB, $clienteB);
        $userA    = $this->insertUser($firmaA, 'userA@test.mx');

        $id = $this->repo->create($this->makeData($firmaB, $casoB, 'B'));

        $this->repo->reassign($firmaA, $id, $userA);

        $row = $this->pdo->query("SELECT asignado_a FROM tareas WHERE id = $id")->fetch();
        $this->assertNull($row['asignado_a'], 'SEGURIDAD: reassign no debe afectar tareas de otras firmas');
    }

    public function test_set_termino_updates_fecha_vencimiento(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'FV'));

        $nuevaFecha = date('Y-m-d', strtotime('+30 days'));
        $this->repo->setTermino($firmaId, $id, $nuevaFecha);

        $row = $this->pdo->query("SELECT fecha_vencimiento FROM tareas WHERE id = $id")->fetch();
        $this->assertSame($nuevaFecha, $row['fecha_vencimiento']);
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'DEL'));

        $this->repo->softDelete($firmaId, $id);

        $row = $this->pdo->query("SELECT deleted_at FROM tareas WHERE id = $id")->fetch();
        $this->assertNotNull($row['deleted_at']);
    }

    /**
     * SEGURIDAD CRÍTICA: softDelete no debe eliminar tareas de otra firma.
     */
    public function test_soft_delete_does_not_delete_tarea_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);
        $casoB    = $this->insertCaso($firmaB, $clienteB);

        $id = $this->repo->create($this->makeData($firmaB, $casoB, 'B'));

        $this->repo->softDelete($firmaA, $id);

        $row = $this->pdo->query("SELECT deleted_at FROM tareas WHERE id = $id")->fetch();
        $this->assertNull($row['deleted_at'], 'SEGURIDAD: softDelete no debe afectar tareas de otras firmas');
    }

    public function test_paginate_excludes_deleted_tareas(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id1 = $this->repo->create($this->makeData($firmaId, $casoId, '1'));
        $id2 = $this->repo->create($this->makeData($firmaId, $casoId, '2'));

        $this->repo->softDelete($firmaId, $id2);

        $resultado = $this->repo->paginate($firmaId, []);
        $this->assertCount(1, $resultado['items']);
    }
}
