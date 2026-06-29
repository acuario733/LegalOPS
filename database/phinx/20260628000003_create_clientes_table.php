<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateClientesTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('clientes', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('firma_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('nombre_razon_social', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('tipo_persona', 'string', ['limit' => 20, 'null' => false, 'default' => 'fisica'])
            ->addColumn('numero_documento', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('tipo_documento', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 320, 'null' => false])
            ->addColumn('telefono', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('direccion', 'text', ['null' => true])
            ->addColumn('estado', 'string', ['limit' => 30, 'null' => false, 'default' => 'activo'])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            // TENANT FILTER: firma_id es crítico para el aislamiento multi-tenant
            ->addIndex(['firma_id'], ['name' => 'idx_clientes_firma'])
            ->addIndex(['firma_id', 'created_at'], ['name' => 'idx_clientes_firma_created'])
            ->addIndex(['firma_id', 'estado'], ['name' => 'idx_clientes_firma_estado'])
            ->addIndex(['email', 'firma_id'], ['name' => 'idx_clientes_email_firma'])
            ->addForeignKey('firma_id', 'firmas', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('clientes')->drop()->save();
    }
}
