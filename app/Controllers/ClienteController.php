<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ClienteService;

final class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = (array) $request->query();
        $page = max(1, (int) $request->query('page', 1));

        return $this->view('clientes/index', [
            'title' => 'Clientes',
            'clientes' => $this->container->get(ClienteService::class)->list($this->firmaId(), $filters, $page),
            'filters' => $filters,
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('clientes/show', [
            'title' => 'Ficha de cliente',
            'cliente' => $this->container->get(ClienteService::class)->find($this->firmaId(), (int) $id),
            'canReveal' => $this->permissions->allows('clientes.revelar', $this->currentUser()),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(ClienteService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Cliente creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(ClienteService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Cliente actualizado correctamente.');
    }

    public function delete(Request $request, string $id): Response
    {
        $this->container->get(ClienteService::class)->delete($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Cliente eliminado correctamente.');
    }

    public function reveal(Request $request, string $id): Response
    {
        $field = (string) $request->input('campo', '');
        $data = $this->container->get(ClienteService::class)->reveal($this->firmaId(), (int) $id, $field, $request);

        return $this->json($data, 'Dato revelado correctamente.');
    }

    public function search(Request $request): Response
    {
        $filters = (array) $request->query();
        $page = max(1, (int) $request->query('page', 1));

        return $this->json($this->container->get(ClienteService::class)->list($this->firmaId(), $filters, $page));
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(ClienteService::class)->find($this->firmaId(), (int) $id));
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
