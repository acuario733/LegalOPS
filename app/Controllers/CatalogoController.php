<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogoService;

final class CatalogoController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = (int) ($this->currentFirma() ?? 0);

        return $this->view('catalogos/index', [
            'title' => 'Catálogos', 'catalogos' => $this->container->get(CatalogoService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(), 'currentUser' => $this->currentUser(),
        ], ($this->currentUser()['tipo'] ?? null) === 'superadmin' ? 'superadmin' : 'app');
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(CatalogoService::class)->create((int) ($this->currentFirma() ?? 0), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Catálogo creado correctamente.', 201);
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(CatalogoService::class)->detail((int) ($this->currentFirma() ?? 0), (int) $id));
    }

    public function storeItem(Request $request, string $id): Response
    {
        $itemId = $this->container->get(CatalogoService::class)->createItem((int) ($this->currentFirma() ?? 0), (int) $id, (array) $request->input(), $request);

        return $this->json(['id' => $itemId], 'Ítem creado correctamente.', 201);
    }

    public function itemStatus(Request $request, string $catalogId, string $itemId): Response
    {
        $this->container->get(CatalogoService::class)->setItemStatus((int) ($this->currentFirma() ?? 0), (int) $catalogId, (int) $itemId, (string) $request->input('estado', 'inactivo'), $request);

        return $this->json(null, 'Estado actualizado correctamente.');
    }

    public function updateItem(Request $request, string $catalogId, string $itemId): Response
    {
        $this->container->get(CatalogoService::class)->updateItem((int) ($this->currentFirma() ?? 0), (int) $catalogId, (int) $itemId, (array) $request->input(), $request);

        return $this->json(null, 'Ítem actualizado correctamente.');
    }
}
