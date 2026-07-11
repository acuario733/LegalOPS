<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class CasoSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['ClienteSeeder', 'UserSeeder'];
    }

    public function run(): void
    {
        $clientes = $this->fetchAll('SELECT id, firma_id FROM clientes ORDER BY id ASC LIMIT 10');
        $abogados = $this->fetchAll('SELECT id, firma_id FROM usuarios ORDER BY id ASC');

        $abogadoByFirma = [];
        foreach ($abogados as $a) {
            $abogadoByFirma[$a['firma_id']] = $a['id'];
        }

        $tipos     = ['civil', 'mercantil', 'laboral', 'penal', 'administrativo'];
        $estados   = ['activo', 'activo', 'activo', 'en_revision', 'cerrado'];
        $prioridades = ['alta', 'media', 'baja', 'critica', 'media'];

        foreach (array_slice($clientes, 0, 5) as $i => $cliente) {
            $this->table('casos')->insert([
                'firma_id'    => $cliente['firma_id'],
                'cliente_id'  => $cliente['id'],
                'abogado_id'  => $abogadoByFirma[$cliente['firma_id']] ?? null,
                'numero_caso' => 'EXP-2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'titulo'      => "Caso #{$i} — Asunto " . $tipos[$i % count($tipos)] . ' del cliente',
                'descripcion' => "Descripción detallada del caso número {$i}. Materia: {$tipos[$i % count($tipos)]}. Generado por seeder.",
                'estado'      => $estados[$i % count($estados)],
                'prioridad'   => $prioridades[$i % count($prioridades)],
                'tipo'        => $tipos[$i % count($tipos)],
                'fecha_inicio' => date('Y-m-d', strtotime("-{$i} months")),
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ])->save();
        }
    }
}
