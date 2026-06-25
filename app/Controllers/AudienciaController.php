<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AudienciaService;
use App\Services\CasoService;
use App\Services\UsuarioService;

final class AudienciaController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('audiencias/index', [
            'title' => 'Audiencias',
            'audiencias' => $this->container->get(AudienciaService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('audiencias/show', [
            'title' => 'Ficha de audiencia',
            'audiencia' => $this->container->get(AudienciaService::class)->find($firmaId, (int) $id),
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(AudienciaService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Audiencia creada correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(AudienciaService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Audiencia actualizada correctamente.');
    }

    public function result(Request $request, string $id): Response
    {
        $this->container->get(AudienciaService::class)->registerResult($this->firmaId(), (int) $id, (string) $request->input('resultado', ''), $request);

        return $this->json(null, 'Resultado registrado correctamente.');
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(AudienciaService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(AudienciaService::class)->find($this->firmaId(), (int) $id));
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
