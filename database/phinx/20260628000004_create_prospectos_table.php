<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateProspectosTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('prospectos', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('firma_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('nombre_empresa', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('nombre_contacto', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('email_contacto', 'string', ['limit' => 320, 'null' => false])
            ->addColumn('telefono', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('estado', 'string', ['limit' => 50, 'null' => false, 'default' => 'nuevo'])
            ->addColumn('fuente', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('notas', 'text', ['null' => true])
            ->addColumn('asignado_a', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            // TENANT FILTER: firma_id es crítico para el aislamiento multi-tenant
            ->addIndex(['firma_id'], ['name' => 'idx_prospectos_firma'])
            ->addIndex(['firma_id', 'estado'], ['name' => 'idx_prospectos_firma_estado'])
            ->addIndex(['firma_id', 'created_at'], ['name' => 'idx_prospectos_firma_created'])
            ->addForeignKey('firma_id', 'firmas', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('asignado_a', 'usuarios', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('prospectos')->drop()->save();
    }
}
