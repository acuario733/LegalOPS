<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\FirmaService;
use App\Services\PlanService;

final class FirmaController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('superadmin/firmas/index', [
            'title' => 'Firmas',
            'firmas' => $this->container->get(FirmaService::class)->all(),
            'planes' => $this->container->get(PlanService::class)->all(),
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

