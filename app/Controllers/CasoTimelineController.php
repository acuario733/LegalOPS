<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoTimelineService;

final class CasoTimelineController extends Controller
{
    public function index(Request $request, string $casoId): Response
    {
        $firmaId = $this->firmaId();
        $service = $this->container->get(CasoTimelineService::class);

        return $this->view('casos/timeline/index', [
            'title' => 'Linea de tiempo',
            'caso' => $service->caseInfo($firmaId, (int) $casoId),
            'eventos' => $service->list($firmaId, (int) $casoId),
            'canPublish' => $this->permissions->allows('timeline.publicar', $this->currentUser()),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request, string $casoId): Response
    {
        $id = $this->container->get(CasoTimelineService::class)->create($this->firmaId(), (int) $casoId, (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Evento creado correctamente.', 201);
    }

    public function update(Request $request, string $casoId, string $id): Response
    {
        $this->container->get(CasoTimelineService::class)->update($this->firmaId(), (int) $casoId, (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Evento actualizado correctamente.');
    }

    public function visibility(Request $request, string $casoId, string $id): Response
    {
        $this->container->get(CasoTimelineService::class)->changeVisibility($this->firmaId(), (int) $casoId, (int) $id, (string) $request->input('visibilidad', ''), $request);

        return $this->json(null, 'Visibilidad actualizada correctamente.');
    }

    public function delete(Request $request, string $casoId, string $id): Response
    {
        $this->container->get(CasoTimelineService::class)->delete($this->firmaId(), (int) $casoId, (int) $id, $request);

        return $this->json(null, 'Evento eliminado correctamente.');
    }

    public function apiList(Request $request, string $casoId): Response
    {
        return $this->json($this->container->get(CasoTimelineService::class)->list($this->firmaId(), (int) $casoId));
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
