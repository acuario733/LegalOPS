<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('usuarios', ['id' => 'id', 'signed' => false]);
        $table
            ->addColumn('firma_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('nombre', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('email', 'string', ['limit' => 320, 'null' => false])
            ->addColumn('email_normalizado', 'string', ['limit' => 320, 'null' => false])
            ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('rol', 'string', ['limit' => 60, 'null' => false, 'default' => 'abogado'])
            ->addColumn('activo', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('must_change_password', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deactivated_at', 'datetime', ['null' => true])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['firma_id'], ['name' => 'idx_usuarios_firma'])
            ->addIndex(['email_normalizado'], ['unique' => true, 'name' => 'uq_usuarios_email'])
            ->addIndex(['firma_id', 'created_at'], ['name' => 'idx_usuarios_firma_created'])
            ->addForeignKey('firma_id', 'firmas', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('usuarios')->drop()->save();
    }
}
