<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateFirmasTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('firmas', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('nombre', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('telefono', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('pais', 'string', ['limit' => 60, 'null' => true, 'default' => 'MX'])
            ->addColumn('timezone', 'string', ['limit' => 60, 'null' => false, 'default' => 'America/Mexico_City'])
            ->addColumn('suspended_at', 'datetime', ['null' => true])
            ->addColumn('suspension_reason', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['nombre', 'deleted_at'], ['name' => 'idx_firmas_nombre'])
            ->addIndex(['slug'], ['unique' => true, 'name' => 'uq_firmas_slug'])
            ->create();
    }

    public function down(): void
    {
        $this->table('firmas')->drop()->save();
    }
}
