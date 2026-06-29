<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ProspectoRepository;

/**
 * Tests de ProspectoRepository.
 *
 * Cubre el ciclo de vida completo de un prospecto y el aislamiento multi-tenant.
 * Los prospectos tienen un campo especial: estado (nuevo → contactado → propuesta → ganado/perdido).
 */
class ProspectoRepositoryTest extends RepositoryTestCase
{
    private ProspectoRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Columnas extra específicas de prospectos
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN nombre_contacto TEXT");
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN cargo_contacto TEXT");
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN sector TEXT");
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN valor_estimado REAL");
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN conversion_probabilidad INTEGER DEFAULT 50");
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN convertido_cliente_id INTEGER");
        $this->pdo->exec("ALTER TABLE prospectos ADD COLUMN convertido_at TEXT");

        $this->repo = new ProspectoRepository($this->pdo);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function makeData(int $firmaId, string $sufijo = ''): array
    {
        return [
            'firma_id'      => $firmaId,
            'nombre_empresa' => "Prospecto Empresa $sufijo",
            'nombre_contacto' => "Contacto $sufijo",
            'email_contacto' => "contacto$sufijo@empresa.mx",
            'telefono'      => '+52 55 9999 0000',
            'estado'        => 'nuevo',
            'fuente'        => 'web',
            'notas'         => 'Interesado en módulo de casos.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_paginate_returns_only_prospectos_of_given_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $this->repo->create($this->makeData($firmaA, 'A1'));
        $this->repo->create($this->makeData($firmaA, 'A2'));
        $this->repo->create($this->makeData($firmaB, 'B1')); // no debe aparecer

        $resultado = $this->repo->paginate($firmaA, []);

        $this->assertCount(2, $resultado['items']);
        $this->assertSame(2, $resultado['total']);

        foreach ($resultado['items'] as $item) {
            $this->assertSame($firmaA, (int) $item['firma_id']);
        }
    }

    public function test_paginate_can_filter_by_estado(): void
    {
        $firmaId = $this->insertFirma();
        $this->repo->create($this->makeData($firmaId, '1')); // estado: nuevo
        $data2 = $this->makeData($firmaId, '2');
        $data2['estado'] = 'contactado';
        $this->repo->create($data2);

        $resultadoNuevos     = $this->repo->paginate($firmaId, ['estado' => 'nuevo']);
        $resultadoContactados = $this->repo->paginate($firmaId, ['estado' => 'contactado']);

        $this->assertCount(1, $resultadoNuevos['items']);
        $this->assertCount(1, $resultadoContactados['items']);
    }

    public function test_find_for_firma_returns_prospecto(): void
    {
        $firmaId = $this->insertFirma();
        $id = $this->repo->create($this->makeData($firmaId, 'X'));

        $prospecto = $this->repo->findForFirma($firmaId, $id);

        $this->assertNotNull($prospecto);
        $this->assertSame($id, (int) $prospecto['id']);
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A no puede leer prospectos de Firma B.
     */
    public function test_find_returns_null_when_prospecto_belongs_to_other_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $idEnFirmaB = $this->repo->create($this->makeData($firmaB, 'B'));

        $resultado = $this->repo->findForFirma($firmaA, $idEnFirmaB);

        $this->assertNull($resultado, 'SEGURIDAD: Firma A no puede leer prospectos de Firma B');
    }

    public function test_create_returns_valid_id(): void
    {
        $firmaId = $this->insertFirma();

        $id = $this->repo->create($this->makeData($firmaId, 'NEW'));

        $this->assertGreaterThan(0, $id);
    }

    public function test_update_modifies_fields(): void
    {
        $firmaId = $this->insertFirma();
        $id      = $this->repo->create($this->makeData($firmaId, 'UPD'));

        $data = $this->makeData($firmaId, 'MOD');
        $data['nombre_empresa'] = 'Empresa Modificada S.A.';
        $this->repo->update($firmaId, $id, $data);

        $row = $this->pdo->query("SELECT nombre_empresa FROM prospectos WHERE id = $id")->fetch();
        $this->assertSame('Empresa Modificada S.A.', $row['nombre_empresa']);
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A no puede modificar prospectos de Firma B.
     */
    public function test_update_does_not_affect_prospecto_of_other_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $id = $this->repo->create($this->makeData($firmaB, 'B'));
        $nombreOriginal = 'Prospecto Empresa B';

        $data = $this->makeData($firmaA, 'HACK');
        $data['nombre_empresa'] = 'Hackeado';
        $this->repo->update($firmaA, $id, $data);

        $row = $this->pdo->query("SELECT nombre_empresa FROM prospectos WHERE id = $id")->fetch();
        $this->assertSame($nombreOriginal, $row['nombre_empresa'], 'SEGURIDAD: update no debe afectar prospectos de otras firmas');
    }

    public function test_set_status_changes_estado(): void
    {
        $firmaId = $this->insertFirma();
        $id      = $this->repo->create($this->makeData($firmaId, 'ST'));

        $this->repo->setStatus($firmaId, $id, 'contactado');

        $row = $this->pdo->query("SELECT estado FROM prospectos WHERE id = $id")->fetch();
        $this->assertSame('contactado', $row['estado']);
    }

    /**
     * SEGURIDAD CRÍTICA: setStatus no debe cambiar el estado de otro tenant.
     */
    public function test_set_status_does_not_affect_other_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $id = $this->repo->create($this->makeData($firmaB, 'B'));

        $this->repo->setStatus($firmaA, $id, 'ganado');

        $row = $this->pdo->query("SELECT estado FROM prospectos WHERE id = $id")->fetch();
        $this->assertSame('nuevo', $row['estado'], 'SEGURIDAD: setStatus no debe afectar prospectos de otras firmas');
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        $firmaId = $this->insertFirma();
        $id      = $this->repo->create($this->makeData($firmaId, 'DEL'));

        $this->repo->softDelete($firmaId, $id);

        $row = $this->pdo->query("SELECT deleted_at FROM prospectos WHERE id = $id")->fetch();
        $this->assertNotNull($row['deleted_at']);
    }

    /**
     * SEGURIDAD CRÍTICA: softDelete no debe eliminar prospectos de otra firma.
     */
    public function test_soft_delete_does_not_delete_other_firma_prospecto(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $id = $this->repo->create($this->makeData($firmaB, 'B'));

        $this->repo->softDelete($firmaA, $id);

        $row = $this->pdo->query("SELECT deleted_at FROM prospectos WHERE id = $id")->fetch();
        $this->assertNull($row['deleted_at'], 'SEGURIDAD: softDelete no debe afectar prospectos de otras firmas');
    }

    public function test_paginate_excludes_deleted_prospectos(): void
    {
        $firmaId = $this->insertFirma();
        $id1 = $this->repo->create($this->makeData($firmaId, '1'));
        $id2 = $this->repo->create($this->makeData($firmaId, '2'));

        $this->repo->softDelete($firmaId, $id2);

        $resultado = $this->repo->paginate($firmaId, []);
        $this->assertCount(1, $resultado['items']);
    }
}
