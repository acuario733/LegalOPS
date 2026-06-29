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

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }
}
