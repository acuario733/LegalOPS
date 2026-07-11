<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogoLookupService;
use App\Services\ExportacionService;
use App\Services\ReporteService;

final class ReporteController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $catalogs = $this->container->get(CatalogoLookupService::class);

        return $this->view('reportes/index', [
            'title' => 'Reportes',
            'reportes' => $this->container->get(ReporteService::class)->available($this->currentUser() ?? []),
            'catalogos' => [
                'tipo_caso' => $catalogs->items($firmaId, 'tipo_caso'),
                'moneda' => $catalogs->items($firmaId, 'moneda'),
            ],
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function export(Request $request, string $tipo): Response
    {
        $export = $this->container->get(ExportacionService::class)->export($this->firmaId(), $tipo, $this->currentUser() ?? [], $request);

        return Response::download($export['path'], $export['name'], 'text/csv; charset=UTF-8');
    }

    public function aging(Request $request): Response
    {
        $data = $this->container->get(ReporteService::class)->getAging(
            $this->firmaId(),
            is_string($request->query('fecha_corte')) ? $request->query('fecha_corte') : null
        );
        if ($request->query('formato') !== 'csv') {
            return $this->json($data);
        }
        $directory = dirname(__DIR__, 2) . '/storage/temp/reports';
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $path = $directory . '/aging-' . bin2hex(random_bytes(8)) . '.csv';
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No fue posible generar el CSV.');
        }
        fputcsv($handle, ['bucket', 'cliente', 'honorario_id', 'numero', 'monto', 'moneda', 'dias_vencido']);
        foreach ($data as $bucket => $group) {
            foreach ($group['items'] as $item) {
                fputcsv($handle, [$bucket, $item['cliente'], $item['honorario_id'], $item['numero'], $item['monto'], $item['moneda'], $item['dias_vencido']]);
            }
        }
        fclose($handle);

        return Response::download($path, 'aging-' . date('Y-m-d') . '.csv', 'text/csv; charset=UTF-8');
    }

    public function crm(Request $request): Response
    {
        return $this->json($this->container->get(ReporteService::class)->getCrm(
            $this->firmaId(),
            is_string($request->query('desde')) ? $request->query('desde') : null,
            is_string($request->query('hasta')) ? $request->query('hasta') : null
        ));
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }
}
