<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class TareaSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['CasoSeeder', 'UserSeeder'];
    }

    public function run(): void
    {
        $casos    = $this->fetchAll('SELECT id, firma_id FROM casos ORDER BY id ASC');
        $abogados = $this->fetchAll('SELECT id, firma_id FROM usuarios ORDER BY id ASC');

        $abogadoByFirma = [];
        foreach ($abogados as $a) {
            $abogadoByFirma[$a['firma_id']] = $a['id'];
        }

        $estados     = ['pendiente', 'en_progreso', 'completada'];
        $prioridades = ['alta', 'media', 'baja'];
        $titulos     = [
            'Revisión de documentación inicial',
            'Presentación de escrito inicial',
            'Seguimiento ante tribunal',
        ];

        foreach ($casos as $caso) {
            foreach (range(0, 2) as $j) {
                $vencimiento = date('Y-m-d', strtotime("+{$j} weeks"));
                $this->table('tareas')->insert([
                    'firma_id'          => $caso['firma_id'],
                    'caso_id'           => $caso['id'],
                    'asignado_a'        => $abogadoByFirma[$caso['firma_id']] ?? null,
                    'titulo'            => $titulos[$j],
                    'descripcion'       => "Tarea #{$j} del caso #{$caso['id']}. Generada por seeder.",
                    'estado'            => $estados[$j % count($estados)],
                    'prioridad'         => $prioridades[$j % count($prioridades)],
                    'fecha_vencimiento' => $vencimiento,
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ])->save();
            }
        }
    }
}
