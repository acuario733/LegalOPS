<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogoLookupService;
use App\Services\CasoService;
use App\Services\ClienteService;
use App\Services\DocumentoService;
use App\Services\GastoService;
use App\Services\HonorarioService;
use App\Services\PagoService;

final class FinanzasController extends Controller
{
    public function honorarios(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('finanzas/honorarios/index', [
            'title' => 'Honorarios',
            'honorarios' => $this->container->get(HonorarioService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'catalogos' => $this->catalogos($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function storeHonorario(Request $request): Response
    {
        $id = $this->container->get(HonorarioService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Honorario creado correctamente.', 201);
    }

    public function updateHonorario(Request $request, string $id): Response
    {
        $this->container->get(HonorarioService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Honorario actualizado correctamente.');
    }

    public function cancelHonorario(Request $request, string $id): Response
    {
        $this->container->get(HonorarioService::class)->cancel($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Honorario cancelado correctamente.');
    }

    public function pagos(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('finanzas/pagos/index', [
            'title' => 'Pagos',
            'pagos' => $this->container->get(PagoService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'honorarios' => $this->container->get(HonorarioService::class)->list($firmaId, [], 1, 100)['items'],
            'catalogos' => $this->catalogos($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function storePago(Request $request): Response
    {
        $id = $this->container->get(PagoService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Pago registrado correctamente.', 201);
    }

    public function revealPago(Request $request, string $id): Response
    {
        return $this->json($this->container->get(PagoService::class)->revealReference($this->firmaId(), (int) $id, $request), 'Referencia revelada correctamente.');
    }

    public function annulPago(Request $request, string $id): Response
    {
        $this->container->get(PagoService::class)->annul($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Pago anulado correctamente.');
    }

    public function gastos(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('finanzas/gastos/index', [
            'title' => 'Gastos',
            'gastos' => $this->container->get(GastoService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'documentos' => $this->container->get(DocumentoService::class)->list($firmaId, [], 1, 100)['items'],
            'catalogos' => $this->catalogos($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function storeGasto(Request $request): Response
    {
        $id = $this->container->get(GastoService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Gasto registrado correctamente.', 201);
    }

    public function updateGasto(Request $request, string $id): Response
    {
        $this->container->get(GastoService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Gasto actualizado correctamente.');
    }

    public function annulGasto(Request $request, string $id): Response
    {
        $this->container->get(GastoService::class)->annul($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Gasto anulado correctamente.');
    }

    public function searchHonorarios(Request $request): Response
    {
        return $this->json($this->container->get(HonorarioService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detailHonorario(Request $request, string $id): Response
    {
        return $this->json($this->container->get(HonorarioService::class)->find($this->firmaId(), (int) $id));
    }

    public function searchPagos(Request $request): Response
    {
        return $this->json($this->container->get(PagoService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detailPago(Request $request, string $id): Response
    {
        return $this->json($this->container->get(PagoService::class)->find($this->firmaId(), (int) $id));
    }

    public function searchGastos(Request $request): Response
    {
        return $this->json($this->container->get(GastoService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detailGasto(Request $request, string $id): Response
    {
        return $this->json($this->container->get(GastoService::class)->find($this->firmaId(), (int) $id));
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }

    /** @return array<string, list<array{codigo: string, etiqueta: string}>> */
    private function catalogos(int $firmaId): array
    {
        $catalogs = $this->container->get(CatalogoLookupService::class);

        return [
            'concepto_honorario' => $catalogs->items($firmaId, 'concepto_honorario'),
            'moneda' => $catalogs->items($firmaId, 'moneda'),
            'metodo_pago' => $catalogs->items($firmaId, 'metodo_pago'),
        ];
    }
}
