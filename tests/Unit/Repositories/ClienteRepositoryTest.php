<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ClienteRepository;
use PDO;

/**
 * Tests de ClienteRepository.
 *
 * Verifica que todos los métodos del repository filtren correctamente por firma_id.
 * Los tests de "cross-firma" son los más importantes para el aislamiento multi-tenant.
 *
 * Usa SQLite :memory: — no requiere MySQL real.
 */
class ClienteRepositoryTest extends RepositoryTestCase
{
    private ClienteRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Extender el esquema con columnas extra que usa ClienteRepository real
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN nombre_normalizado TEXT");
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN documento_normalizado TEXT");
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN documento_hash TEXT");
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN origen TEXT DEFAULT 'manual'");
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN observaciones TEXT");
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN tratamiento_datos_autorizado INTEGER DEFAULT 0");
        $this->pdo->exec("ALTER TABLE clientes ADD COLUMN autorizacion_tratamiento_at TEXT");

        $this->repo = new ClienteRepository($this->pdo);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers específicos de esta suite
    // ─────────────────────────────────────────────────────────────────────────

    private function createClienteData(int $firmaId, string $sufijo = ''): array
    {
        return [
            'firma_id'                    => $firmaId,
            'tipo_persona'                => 'moral',
            'nombre_razon_social'         => "Cliente Test $sufijo",
            'nombre_normalizado'          => strtolower("cliente test $sufijo"),
            'tipo_documento'              => 'rfc',
            'numero_documento'            => "RFC$sufijo",
            'documento_normalizado'       => "rfc$sufijo",
            'documento_hash'              => hash('sha256', "rfc$sufijo"),
            'email'                       => "cliente$sufijo@test.mx",
            'telefono'                    => '+52 55 1234 5678',
            'direccion'                   => 'Calle Falsa 123',
            'estado'                      => 'activo',
            'origen'                      => 'manual',
            'observaciones'               => null,
            'tratamiento_datos_autorizado' => 0,
            'autorizacion_tratamiento_at'  => null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_paginate_returns_only_clients_of_given_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $this->repo->create($this->createClienteData($firmaA, 'A1'));
        $this->repo->create($this->createClienteData($firmaA, 'A2'));
        $this->repo->create($this->createClienteData($firmaB, 'B1')); // no debe aparecer

        $resultado = $this->repo->paginate($firmaA, []);

        $this->assertCount(2, $resultado['items'], 'Solo deben aparecer clientes de la firma A');
        $this->assertSame(2, $resultado['total']);

        foreach ($resultado['items'] as $item) {
            $this->assertSame($firmaA, (int) $item['firma_id']);
        }
    }

    public function test_paginate_returns_paginated_results(): void
    {
        $firmaId = $this->insertFirma();

        for ($i = 1; $i <= 5; $i++) {
            $this->repo->create($this->createClienteData($firmaId, (string) $i));
        }

        $page1 = $this->repo->paginate($firmaId, [], page: 1, perPage: 3);
        $page2 = $this->repo->paginate($firmaId, [], page: 2, perPage: 3);

        $this->assertCount(3, $page1['items']);
        $this->assertCount(2, $page2['items']);
        $this->assertSame(5, $page1['total']);
    }

    public function test_paginate_returns_empty_array_when_no_clients(): void
    {
        $firmaId = $this->insertFirma();

        $resultado = $this->repo->paginate($firmaId, []);

        $this->assertCount(0, $resultado['items']);
        $this->assertSame(0, $resultado['total']);
    }

    public function test_find_for_firma_returns_client_by_id_and_firma(): void
    {
        $firmaId = $this->insertFirma();
        $id = $this->repo->create($this->createClienteData($firmaId, 'X'));

        $cliente = $this->repo->findForFirma($firmaId, $id);

        $this->assertNotNull($cliente);
        $this->assertSame($id, (int) $cliente['id']);
        $this->assertSame($firmaId, (int) $cliente['firma_id']);
    }

    /**
     * SEGURIDAD CRÍTICA: Un cliente de Firma B NO debe ser visible para Firma A.
     * Esto es la garantía fundamental del multi-tenancy.
     */
    public function test_find_returns_null_when_client_belongs_to_other_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $idEnFirmaB = $this->repo->create($this->createClienteData($firmaB, 'B'));

        // Firma A intenta leer un cliente de Firma B
        $resultado = $this->repo->findForFirma($firmaA, $idEnFirmaB);

        $this->assertNull($resultado, 'SEGURIDAD: Firma A NO debe poder leer clientes de Firma B');
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        $firmaId = $this->insertFirma();

        $resultado = $this->repo->findForFirma($firmaId, 99999);

        $this->assertNull($resultado);
    }

    public function test_create_inserts_client_and_returns_id(): void
    {
        $firmaId = $this->insertFirma();

        $id = $this->repo->create($this->createClienteData($firmaId, 'NEW'));

        $this->assertGreaterThan(0, $id, 'create() debe retornar un ID entero positivo');

        $row = $this->pdo->query("SELECT * FROM clientes WHERE id = $id")->fetch();
        $this->assertNotFalse($row, 'El cliente debe existir en la BD');
    }

    public function test_create_assigns_firma_id_automatically(): void
    {
        $firmaId = $this->insertFirma();

        $id  = $this->repo->create($this->createClienteData($firmaId, 'FID'));
        $row = $this->pdo->query("SELECT firma_id FROM clientes WHERE id = $id")->fetch();

        $this->assertSame($firmaId, (int) $row['firma_id']);
    }

    public function test_update_modifies_existing_client(): void
    {
        $firmaId = $this->insertFirma();
        $id      = $this->repo->create($this->createClienteData($firmaId, 'UPD'));

        $updateData = $this->createClienteData($firmaId, 'UPDATED');
        $updateData['nombre_razon_social'] = 'Nombre Actualizado S.A.';

        $this->repo->update($firmaId, $id, $updateData);

        $row = $this->pdo->query("SELECT nombre_razon_social FROM clientes WHERE id = $id")->fetch();
        $this->assertSame('Nombre Actualizado S.A.', $row['nombre_razon_social']);
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A NO debe poder modificar clientes de Firma B.
     */
    public function test_update_does_not_affect_client_of_other_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $idEnFirmaB = $this->repo->create($this->createClienteData($firmaB, 'B'));
        $originalNombre = 'Cliente Test B';

        // Firma A intenta actualizar un cliente de Firma B
        $updateData = $this->createClienteData($firmaA, 'HACK');
        $updateData['nombre_razon_social'] = 'Hackeado';
        $this->repo->update($firmaA, $idEnFirmaB, $updateData);

        $row = $this->pdo->query("SELECT nombre_razon_social FROM clientes WHERE id = $idEnFirmaB")->fetch();
        $this->assertSame($originalNombre, $row['nombre_razon_social'], 'SEGURIDAD: update no debe afectar clientes de otras firmas');
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        $firmaId = $this->insertFirma();
        $id      = $this->repo->create($this->createClienteData($firmaId, 'DEL'));

        $this->repo->softDelete($firmaId, $id);

        $row = $this->pdo->query("SELECT deleted_at FROM clientes WHERE id = $id")->fetch();
        $this->assertNotNull($row['deleted_at'], 'softDelete debe setear deleted_at');
    }

    /**
     * SEGURIDAD CRÍTICA: Firma A NO debe poder eliminar clientes de Firma B.
     */
    public function test_soft_delete_does_not_delete_client_of_other_firma(): void
    {
        $firmaA = $this->insertFirma('Firma A');
        $firmaB = $this->insertFirma('Firma B');

        $idEnFirmaB = $this->repo->create($this->createClienteData($firmaB, 'B'));

        // Firma A intenta eliminar un cliente de Firma B
        $this->repo->softDelete($firmaA, $idEnFirmaB);

        $row = $this->pdo->query("SELECT deleted_at FROM clientes WHERE id = $idEnFirmaB")->fetch();
        $this->assertNull($row['deleted_at'], 'SEGURIDAD: softDelete no debe afectar clientes de otras firmas');
    }

    public function test_paginate_excludes_soft_deleted_clients(): void
    {
        $firmaId = $this->insertFirma();
        $id1 = $this->repo->create($this->createClienteData($firmaId, '1'));
        $id2 = $this->repo->create($this->createClienteData($firmaId, '2'));

        $this->repo->softDelete($firmaId, $id2);

        $resultado = $this->repo->paginate($firmaId, []);
        $this->assertCount(1, $resultado['items'], 'Clientes eliminados no deben aparecer en el listado');
    }
}
