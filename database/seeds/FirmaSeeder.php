<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class FirmaSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('firmas')->insert([
            [
                'nombre'   => 'García & Asociados S.C.',
                'slug'     => 'garcia-asociados',
                'email'    => 'contacto@garcia-asociados.mx',
                'telefono' => '+52 55 1234 5678',
                'pais'     => 'MX',
                'timezone' => 'America/Mexico_City',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nombre'   => 'Rodríguez Peña Abogados',
                'slug'     => 'rodriguez-pena',
                'email'    => 'info@rodriguezpena.mx',
                'telefono' => '+52 33 9876 5432',
                'pais'     => 'MX',
                'timezone' => 'America/Monterrey',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ])->save();
    }
}
