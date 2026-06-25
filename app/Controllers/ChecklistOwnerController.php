<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ChecklistOwnerService;
use App\Services\FirmaService;

final class ChecklistOwnerController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('superadmin/checklist/index', [
            'title' => 'Checklist owner',
            'checklists' => $this->container->get(ChecklistOwnerService::class)->all(),
            'firmas' => $this->container->get(FirmaService::class)->all(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function store(Request $request): Response
    {
        $firmaId = $request->input('firma_id') === '' ? null : (int) $request->input('firma_id');
        $id = $this->container->get(ChecklistOwnerService::class)->create($firmaId, (string) $request->input('titulo', ''), $request);

        return $this->json(['id' => $id], 'Checklist creado correctamente.', 201);
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('superadmin/checklist/show', [
            'title' => 'Checklist owner',
            'checklist' => $this->container->get(ChecklistOwnerService::class)->find((int) $id),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function evaluate(Request $request, string $id, string $itemId): Response
    {
        $this->container->get(ChecklistOwnerService::class)->evaluate((int) $id, (int) $itemId, (array) $request->input(), $request);

        return $this->json(null, 'Item evaluado correctamente.');
    }

    public function decide(Request $request, string $id): Response
    {
        $this->container->get(ChecklistOwnerService::class)->decide((int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Decision owner registrada.');
    }
}
