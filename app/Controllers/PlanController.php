<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\FirmaService;
use App\Services\LimitePlanService;
use App\Services\PlanService;

final class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('superadmin/planes/index', [
            'title' => 'Planes y límites',
            'planes' => $this->container->get(PlanService::class)->all(),
            'firmas' => $this->container->get(FirmaService::class)->all(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(PlanService::class)->create((array) $request->input(), $request);

        return $this->json(['id' => $id], 'Plan creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(PlanService::class)->update((int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Plan actualizado correctamente.');
    }

    public function assign(Request $request): Response
    {
        $this->container->get(PlanService::class)->assign(
            (int) $request->input('firma_id'),
            (int) $request->input('plan_id'),
            (array) $request->input(),
            $request
        );

        return $this->json(null, 'Plan asignado correctamente.');
    }

    public function overrideLimit(Request $request): Response
    {
        $this->container->get(LimitePlanService::class)->override(
            (int) $request->input('firma_id'),
            (int) $request->input('plan_id'),
            (string) $request->input('recurso'),
            $request->input('limite') === '' ? null : (int) $request->input('limite'),
            (string) $request->input('politica', 'block'),
            (string) $request->input('motivo', ''),
            $request
        );

        return $this->json(null, 'Excepción de límite actualizada.');
    }
}
