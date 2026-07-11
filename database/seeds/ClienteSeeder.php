<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class ClienteSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['FirmaSeeder'];
    }

    public function run(): void
    {
        $firmas = $this->fetchAll('SELECT id FROM firmas ORDER BY id ASC LIMIT 2');

        $clientesPorFirma = [
            [
                ['nombre_razon_social' => 'Constructora Hernández S.A. de C.V.', 'email' => 'legal@constructorahernandez.mx', 'tipo_persona' => 'moral', 'numero_documento' => 'CHE010101AAA', 'telefono' => '+52 55 1111 2222'],
                ['nombre_razon_social' => 'Roberto Jiménez Morales', 'email' => 'rjimenez@gmail.com', 'tipo_persona' => 'fisica', 'numero_documento' => 'JIMR850315HDF', 'telefono' => '+52 55 3333 4444'],
                ['nombre_razon_social' => 'Distribuidora López & Hijos S.C.', 'email' => 'contacto@lopezehijos.mx', 'tipo_persona' => 'moral', 'numero_documento' => 'DLH920601CDF', 'telefono' => '+52 55 5555 6666'],
                ['nombre_razon_social' => 'María Elena Vásquez Torres', 'email' => 'mvasquez@hotmail.com', 'tipo_persona' => 'fisica', 'numero_documento' => 'VATM780920MDF', 'telefono' => '+52 55 7777 8888'],
                ['nombre_razon_social' => 'Farmacia Central S. de R.L.', 'email' => 'gerencia@farmaciacentral.mx', 'tipo_persona' => 'moral', 'numero_documento' => 'FCE000101MMX', 'telefono' => '+52 55 9999 0000'],
            ],
            [
                ['nombre_razon_social' => 'Inmobiliaria San Pedro S.A.', 'email' => 'legal@sanpedro.mx', 'tipo_persona' => 'moral', 'numero_documento' => 'ISP010101NLE', 'telefono' => '+52 81 1111 2222'],
                ['nombre_razon_social' => 'Jorge Alberto Ramírez Soto', 'email' => 'jramirez@empresas.mx', 'tipo_persona' => 'fisica', 'numero_documento' => 'RASJ760428HNL', 'telefono' => '+52 81 3333 4444'],
                ['nombre_razon_social' => 'Transportes del Norte S.A. de C.V.', 'email' => 'admin@transportesnorte.mx', 'tipo_persona' => 'moral', 'numero_documento' => 'TNO890601ANL', 'telefono' => '+52 81 5555 6666'],
                ['nombre_razon_social' => 'Patricia Leal Garza', 'email' => 'pleal@correo.mx', 'tipo_persona' => 'fisica', 'numero_documento' => 'LEGP901120MNL', 'telefono' => '+52 81 7777 8888'],
                ['nombre_razon_social' => 'Alimentos Regios S. de R.L. de C.V.', 'email' => 'legal@alimentosregios.mx', 'tipo_persona' => 'moral', 'numero_documento' => 'ARE050505MNL', 'telefono' => '+52 81 9999 0000'],
            ],
        ];

        foreach ($firmas as $i => $firma) {
            foreach ($clientesPorFirma[$i] as $cliente) {
                $this->table('clientes')->insert(array_merge($cliente, [
                    'firma_id'   => $firma['id'],
                    'estado'     => 'activo',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]))->save();
            }
        }
    }
}
