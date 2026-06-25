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

final class TerminoController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('terminos/index', [
            'title' => 'Terminos',
            'terminos' => $this->container->get(TerminoService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'tareas' => $this->container->get(TareaService::class)->list($firmaId, [], 1, 100)['items'],
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('terminos/show', [
            'title' => 'Ficha de termino',
            'termino' => $this->container->get(TerminoService::class)->find($firmaId, (int) $id),
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'tareas' => $this->container->get(TareaService::class)->list($firmaId, [], 1, 100)['items'],
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(TerminoService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Termino creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(TerminoService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Termino actualizado correctamente.');
    }

    public function complete(Request $request, string $id): Response
    {
        $this->container->get(TerminoService::class)->complete($this->firmaId(), (int) $id, (string) $request->input('observacion', ''), $request);

        return $this->json(null, 'Termino marcado como cumplido.');
    }

    public function delete(Request $request, string $id): Response
    {
        $this->container->get(TerminoService::class)->delete($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Termino eliminado correctamente.');
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(TerminoService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(TerminoService::class)->find($this->firmaId(), (int) $id));
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
