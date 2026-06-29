<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCasosTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('casos', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('firma_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('cliente_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('abogado_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('numero_caso', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('titulo', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('descripcion', 'text', ['null' => false])
            ->addColumn('estado', 'string', ['limit' => 50, 'null' => false, 'default' => 'activo'])
            ->addColumn('prioridad', 'string', ['limit' => 20, 'null' => false, 'default' => 'media'])
            ->addColumn('tipo', 'string', ['limit' => 80, 'null' => true])
            ->addColumn('fecha_inicio', 'date', ['null' => true])
            ->addColumn('fecha_cierre', 'date', ['null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            // TENANT FILTER: firma_id es crítico para el aislamiento multi-tenant
            ->addIndex(['firma_id'], ['name' => 'idx_casos_firma'])
            ->addIndex(['firma_id', 'estado'], ['name' => 'idx_casos_firma_estado'])
            ->addIndex(['firma_id', 'prioridad'], ['name' => 'idx_casos_firma_prioridad'])
            ->addIndex(['firma_id', 'created_at'], ['name' => 'idx_casos_firma_created'])
            ->addIndex(['cliente_id'], ['name' => 'idx_casos_cliente'])
            ->addForeignKey('firma_id', 'firmas', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('cliente_id', 'clientes', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('abogado_id', 'usuarios', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('casos')->drop()->save();
    }
}
