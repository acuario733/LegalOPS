<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoService;
use App\Services\ClienteService;
use App\Services\UsuarioService;

final class CasoController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('casos/index', [
            'title' => 'Casos',
            'casos' => $this->container->get(CasoService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('casos/show', [
            'title' => 'Ficha de caso',
            'caso' => $this->container->get(CasoService::class)->find($firmaId, (int) $id),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(CasoService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Caso creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(CasoService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Caso actualizado correctamente.');
    }

    public function close(Request $request, string $id): Response
    {
        $this->container->get(CasoService::class)->close($this->firmaId(), (int) $id, (string) $request->input('motivo', ''), $request);

        return $this->json(null, 'Caso cerrado correctamente.');
    }

    public function archive(Request $request, string $id): Response
    {
        $this->container->get(CasoService::class)->archive($this->firmaId(), (int) $id, (string) $request->input('motivo', ''), $request);

        return $this->json(null, 'Caso archivado correctamente.');
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(CasoService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(CasoService::class)->find($this->firmaId(), (int) $id));
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
