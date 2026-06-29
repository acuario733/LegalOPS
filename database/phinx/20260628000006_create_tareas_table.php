<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTareasTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('tareas', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('firma_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('caso_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('asignado_a', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('titulo', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('descripcion', 'text', ['null' => true])
            ->addColumn('estado', 'string', ['limit' => 50, 'null' => false, 'default' => 'pendiente'])
            ->addColumn('prioridad', 'string', ['limit' => 20, 'null' => false, 'default' => 'media'])
            ->addColumn('fecha_vencimiento', 'date', ['null' => false])
            ->addColumn('completada_at', 'datetime', ['null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            // TENANT FILTER: firma_id es crítico para el aislamiento multi-tenant
            ->addIndex(['firma_id'], ['name' => 'idx_tareas_firma'])
            ->addIndex(['firma_id', 'estado'], ['name' => 'idx_tareas_firma_estado'])
            ->addIndex(['firma_id', 'fecha_vencimiento'], ['name' => 'idx_tareas_firma_vencimiento'])
            ->addIndex(['firma_id', 'created_at'], ['name' => 'idx_tareas_firma_created'])
            ->addIndex(['caso_id'], ['name' => 'idx_tareas_caso'])
            ->addForeignKey('firma_id', 'firmas', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('caso_id', 'casos', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('asignado_a', 'usuarios', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('tareas')->drop()->save();
    }
}
