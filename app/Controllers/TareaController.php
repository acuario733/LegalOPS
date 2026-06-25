<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoService;
use App\Services\TareaService;
use App\Services\TerminoService;
use App\Services\UsuarioService;

final class TareaController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('tareas/index', [
            'title' => 'Tareas',
            'tareas' => $this->container->get(TareaService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'terminos' => $this->container->get(TerminoService::class)->allForSelect($firmaId),
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('tareas/show', [
            'title' => 'Ficha de tarea',
            'tarea' => $this->container->get(TareaService::class)->find($firmaId, (int) $id),
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'terminos' => $this->container->get(TerminoService::class)->allForSelect($firmaId),
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(TareaService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Tarea creada correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(TareaService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Tarea actualizada correctamente.');
    }

    public function reassign(Request $request, string $id): Response
    {
        $this->container->get(TareaService::class)->reassign($this->firmaId(), (int) $id, (int) $request->input('responsable_usuario_id', 0), $request);

        return $this->json(null, 'Tarea reasignada correctamente.');
    }

    public function status(Request $request, string $id): Response
    {
        $this->container->get(TareaService::class)->changeStatus($this->firmaId(), (int) $id, (string) $request->input('estado', ''), $request);

        return $this->json(null, 'Estado de tarea actualizado correctamente.');
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(TareaService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(TareaService::class)->find($this->firmaId(), (int) $id));
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
