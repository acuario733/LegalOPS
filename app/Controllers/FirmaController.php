<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CommercialStatusService;
use App\Services\FirmaService;
use App\Services\PlanService;

final class FirmaController extends Controller
{
    public function index(Request $request): Response
    {
        $firmas = $this->container->get(FirmaService::class)->all();
        $plans = $this->container->get(PlanService::class);
        $historialComercial = [];
        foreach ($firmas as $firma) {
            $historialComercial[(int) $firma['id']] = $plans->commercialHistory((int) $firma['id'], 8);
        }

        return $this->view('superadmin/firmas/index', [
            'title' => 'Firmas',
            'firmas' => $firmas,
            'planes' => $plans->all(),
            'historialComercial' => $historialComercial,
            'commercialStatus' => $this->container->get(CommercialStatusService::class),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(FirmaService::class)->create((array) $request->input(), $request);

        return $this->json(['id' => $id], 'Firma creada correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(FirmaService::class)->update((int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Firma actualizada correctamente.');
    }

    public function billing(Request $request, string $id): Response
    {
        $this->container->get(FirmaService::class)->updateBilling((int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Facturacion comercial actualizada.');
    }

    public function automaticSuspensions(Request $request): Response
    {
        $result = $this->container->get(FirmaService::class)->runAutomaticSuspensions($request);

        return $this->json($result, sprintf(
            'Suspension automatica revisada: %d evaluadas, %d suspendidas.',
            $result['checked'],
            $result['suspended']
        ));
    }

    public function suspend(Request $request, string $id): Response
    {
        $this->container->get(FirmaService::class)->suspend((int) $id, (string) $request->input('motivo', ''), $request);

        return $this->json(null, 'Firma suspendida.');
    }

    public function reactivate(Request $request, string $id): Response
    {
        $this->container->get(FirmaService::class)->reactivate((int) $id, $request);

        return $this->json(null, 'Firma reactivada.');
    }

    public function usage(Request $request, string $id): Response
    {
        return $this->json($this->container->get(FirmaService::class)->usage((int) $id));
    }
}
