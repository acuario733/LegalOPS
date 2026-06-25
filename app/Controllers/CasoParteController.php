<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoParteService;

final class CasoParteController extends Controller
{
    public function index(Request $request, string $casoId): Response
    {
        $firmaId = $this->firmaId();
        $service = $this->container->get(CasoParteService::class);

        return $this->view('casos/partes/index', [
            'title' => 'Partes procesales',
            'caso' => $service->caseInfo($firmaId, (int) $casoId),
            'partes' => $service->list($firmaId, (int) $casoId),
            'canReveal' => $this->permissions->allows('partes.revelar', $this->currentUser()),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request, string $casoId): Response
    {
        $id = $this->container->get(CasoParteService::class)->create($this->firmaId(), (int) $casoId, (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Parte creada correctamente.', 201);
    }

    public function update(Request $request, string $casoId, string $id): Response
    {
        $this->container->get(CasoParteService::class)->update($this->firmaId(), (int) $casoId, (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Parte actualizada correctamente.');
    }

    public function delete(Request $request, string $casoId, string $id): Response
    {
        $this->container->get(CasoParteService::class)->delete($this->firmaId(), (int) $casoId, (int) $id, $request);

        return $this->json(null, 'Parte eliminada correctamente.');
    }

    public function reveal(Request $request, string $casoId, string $id): Response
    {
        $data = $this->container->get(CasoParteService::class)->reveal($this->firmaId(), (int) $casoId, (int) $id, (string) $request->input('campo', ''), $request);

        return $this->json($data, 'Dato revelado correctamente.');
    }

    public function apiList(Request $request, string $casoId): Response
    {
        return $this->json($this->container->get(CasoParteService::class)->list($this->firmaId(), (int) $casoId));
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
