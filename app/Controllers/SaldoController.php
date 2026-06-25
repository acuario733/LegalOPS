<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoService;
use App\Services\ClienteService;
use App\Services\SaldoService;

final class SaldoController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $clienteId = $this->nullableInt($request->query('cliente_id'));
        $casoId = $this->nullableInt($request->query('caso_id'));

        return $this->view('finanzas/saldos/index', [
            'title' => 'Saldos',
            'saldos' => $this->container->get(SaldoService::class)->listByCliente($firmaId, max(1, (int) $request->query('page', 1))),
            'resumen' => $this->container->get(SaldoService::class)->resumen($firmaId, $clienteId, $casoId),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function search(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $clienteId = $this->nullableInt($request->query('cliente_id'));
        $casoId = $this->nullableInt($request->query('caso_id'));

        return $this->json([
            'saldos' => $this->container->get(SaldoService::class)->listByCliente($firmaId, max(1, (int) $request->query('page', 1))),
            'resumen' => $this->container->get(SaldoService::class)->resumen($firmaId, $clienteId, $casoId),
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
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
