<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class UserSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['FirmaSeeder'];
    }

    public function run(): void
    {
        $firmas = $this->fetchAll('SELECT id FROM firmas ORDER BY id ASC LIMIT 2');

        foreach ($firmas as $i => $firma) {
            $this->table('usuarios')->insert([
                'firma_id'           => $firma['id'],
                'nombre'             => $i === 0 ? 'Lic. Carlos García López' : 'Dra. Ana Rodríguez Peña',
                'email'              => $i === 0 ? 'carlos@garcia-asociados.mx' : 'ana@rodriguezpena.mx',
                'email_normalizado'  => $i === 0 ? 'carlos@garcia-asociados.mx' : 'ana@rodriguezpena.mx',
                'password'           => password_hash('LegalOPS2026!', PASSWORD_BCRYPT, ['cost' => 10]),
                'rol'                => 'admin',
                'activo'             => 1,
                'must_change_password' => 0,
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ])->save();
        }
    }
}
