<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class ProspectoSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['FirmaSeeder'];
    }

    public function run(): void
    {
        $firmas = $this->fetchAll('SELECT id FROM firmas ORDER BY id ASC LIMIT 2');

        $estados = ['nuevo', 'contactado', 'propuesta', 'negociacion', 'ganado', 'perdido'];

        foreach ($firmas as $i => $firma) {
            for ($j = 0; $j < 3; $j++) {
                $idx = ($i * 3) + $j;
                $this->table('prospectos')->insert([
                    'firma_id'        => $firma['id'],
                    'nombre_empresa'  => "Empresa Prospecto $idx S.A.",
                    'nombre_contacto' => "Contacto $idx Apellido",
                    'email_contacto'  => "prospecto$idx@empresa$idx.mx",
                    'telefono'        => '+52 55 ' . str_pad((string) ($idx * 1111), 8, '0', STR_PAD_LEFT),
                    'estado'          => $estados[$idx % count($estados)],
                    'fuente'          => $j === 0 ? 'referido' : ($j === 1 ? 'web' : 'redes_sociales'),
                    'notas'           => "Prospecto generado por seeder. Índice: $idx.",
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ])->save();
            }
        }
    }
}
