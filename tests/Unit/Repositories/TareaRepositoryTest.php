<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\TareaRepository;

/**
 * Tests de TareaRepository.
 *
 * Las tareas tienen estado, reasignación, término (opcional) y están anidadas
 * dentro de un Caso que a su vez pertenece a una Firma (triple FK).
 *
 * Reescrito el 2026-07-11: el archivo anterior probaba una API vieja de TareaRepository
 * (columnas y firmas de método que ya no existen — ver docs/IMPLEMENTACION_FASES.md).
 * Este archivo se verificó columna por columna contra database/migrations/0208_create_tareas.sql
 * y método por método contra app/Repositories/TareaRepository.php.
 */
class TareaRepositoryTest extends RepositoryTestCase
{
    private TareaRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // TareaRepository::paginate()/findForFirma() hacen LEFT JOIN terminos —
        // la tabla no existe en el esquema base compartido (no la usa ningún otro
        // repository testeado hoy). Solo necesita existir para el JOIN; ningún
        // test de este archivo inserta ni verifica filas de terminos.
        $this->pdo->exec('
            CREATE TABLE terminos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                firma_id INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                titulo_normalizado TEXT
            )
        ');

        // Columnas reales de "tareas" que no están en el esquema base compartido
        // (el esquema base de RepositoryTestCase quedó con nombres antiguos:
        // asignado_a, completada_at — se dejan sin usar, no rompen nada).
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN termino_id INTEGER');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN responsable_usuario_id INTEGER');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN titulo_normalizado TEXT');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN completed_at TEXT');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN completed_by_usuario_id INTEGER');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN reassigned_at TEXT');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN reassigned_from_usuario_id INTEGER');
        $this->pdo->exec('ALTER TABLE tareas ADD COLUMN reassigned_to_usuario_id INTEGER');

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

    /** @return array<string, mixed> */
    private function makeData(int $firmaId, int $casoId, string $sufijo = ''): array
    {
        return [
            'firma_id'               => $firmaId,
            'caso_id'                => $casoId,
            'termino_id'             => null,
            'responsable_usuario_id' => null,
            'titulo'                 => "Tarea Test $sufijo",
            'titulo_normalizado'     => strtolower("tarea test $sufijo"),
            'descripcion'            => "Desc tarea $sufijo",
            'prioridad'              => 'media',
            'estado'                 => 'pendiente',
            'fecha_vencimiento'      => date('Y-m-d', strtotime('+7 days')),
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

    public function test_update_modifies_titulo(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'UPD'));

        $data = $this->makeData($firmaId, $casoId, 'MOD');
        unset($data['firma_id']);
        $data['titulo'] = 'Título Actualizado';
        $this->repo->update($firmaId, $id, $data);

        $row = $this->pdo->query("SELECT titulo FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('Título Actualizado', $row['titulo']);
    }

    public function test_change_status_updates_estado(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $userId = $this->insertUser($firmaId);
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'ST'));

        $this->repo->changeStatus($firmaId, $id, 'en_proceso', $userId);

        $row = $this->pdo->query("SELECT estado FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('en_proceso', $row['estado']);
    }

    public function test_change_status_to_completada_sets_completed_at(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $userId = $this->insertUser($firmaId);
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'COMP'));

        $this->repo->changeStatus($firmaId, $id, 'completada', $userId);

        $row = $this->pdo->query("SELECT estado, completed_at, completed_by_usuario_id FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('completada', $row['estado']);
        $this->assertNotNull($row['completed_at'], 'Al completar debe setearse completed_at');
        $this->assertSame($userId, (int) $row['completed_by_usuario_id']);
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
        $userA    = $this->insertUser($firmaA, 'userA@test.mx');

        $id = $this->repo->create($this->makeData($firmaB, $casoB, 'B'));

        $this->repo->changeStatus($firmaA, $id, 'completada', $userA);

        $row = $this->pdo->query("SELECT estado FROM tareas WHERE id = $id")->fetch();
        $this->assertSame('pendiente', $row['estado'], 'SEGURIDAD: changeStatus no debe afectar tareas de otras firmas');
    }

    public function test_reassign_changes_responsable(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id     = $this->repo->create($this->makeData($firmaId, $casoId, 'RA'));
        $fromId = $this->insertUser($firmaId, 'origen@test.mx');
        $toId   = $this->insertUser($firmaId, 'destino@test.mx');

        $this->repo->reassign($firmaId, $id, $fromId, $toId);

        $row = $this->pdo->query("SELECT responsable_usuario_id, reassigned_from_usuario_id, reassigned_to_usuario_id FROM tareas WHERE id = $id")->fetch();
        $this->assertSame($toId, (int) $row['responsable_usuario_id']);
        $this->assertSame($fromId, (int) $row['reassigned_from_usuario_id']);
        $this->assertSame($toId, (int) $row['reassigned_to_usuario_id']);
    }

    /**
     * SEGURIDAD CRÍTICA: reassign no puede afectar tareas de otra firma.
     */
    public function test_reassign_does_not_affect_tarea_of_other_firma(): void
    {
        $firmaA   = $this->insertFirma('Firma A');
        $firmaB   = $this->insertFirma('Firma B');
        $clienteB = $this->insertCliente($firmaB);
        $casoB    = $this->insertCaso($firmaB, $clienteB);
        $userA    = $this->insertUser($firmaA, 'userA@test.mx');

        $id = $this->repo->create($this->makeData($firmaB, $casoB, 'B'));

        $this->repo->reassign($firmaA, $id, null, $userA);

        $row = $this->pdo->query("SELECT responsable_usuario_id FROM tareas WHERE id = $id")->fetch();
        $this->assertNull($row['responsable_usuario_id'], 'SEGURIDAD: reassign no debe afectar tareas de otras firmas');
    }

    public function test_set_termino_updates_termino_id(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'FV'));

        $this->repo->setTermino($firmaId, $id, 42);

        $row = $this->pdo->query("SELECT termino_id FROM tareas WHERE id = $id")->fetch();
        $this->assertSame(42, (int) $row['termino_id']);
    }

    public function test_clear_termino_if_matches_clears_only_matching_termino(): void
    {
        [$firmaId, $casoId] = $this->setupFirmaConCaso();
        $id = $this->repo->create($this->makeData($firmaId, $casoId, 'CT'));
        $this->repo->setTermino($firmaId, $id, 42);

        $this->repo->clearTerminoIfMatches($firmaId, $id, 99);
        $row = $this->pdo->query("SELECT termino_id FROM tareas WHERE id = $id")->fetch();
        $this->assertSame(42, (int) $row['termino_id'], 'No debe limpiar si el termino_id no coincide');

        $this->repo->clearTerminoIfMatches($firmaId, $id, 42);
        $row = $this->pdo->query("SELECT termino_id FROM tareas WHERE id = $id")->fetch();
        $this->assertNull($row['termino_id'], 'Debe limpiar cuando el termino_id coincide');
    }
}
