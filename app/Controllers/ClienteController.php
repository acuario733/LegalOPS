<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogoLookupService;
use App\Services\ClienteService;
use App\Services\GdprService;
use App\Validators\ClienteValidator;

final class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = (array) $request->query();
        $page = max(1, (int) $request->query('page', 1));

        return $this->view('clientes/index', [
            'title' => 'Clientes',
            'clientes' => $this->container->get(ClienteService::class)->list($this->firmaId(), $filters, $page),
            'catalogos' => $this->catalogos(),
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
            'catalogos' => $this->catalogos(),
            'canReveal' => $this->permissions->allows('clientes.revelar', $this->currentUser()),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = new ClienteValidator();
        if (!$validator->validateCreate((array) $request->input())) {
            return $this->json(['validation' => $validator->errors()], 'Datos inválidos. Por favor revisa los campos.', 422);
        }

        $result = $this->container->get(ClienteService::class)->createWithDuplicateInfo($this->firmaId(), (array) $request->input(), $request);

        return $this->json($result, 'Cliente creado correctamente.', $result['posibles_duplicados'] === [] ? 201 : 200);
    }

    public function update(Request $request, string $id): Response
    {
        $validator = new ClienteValidator();
        if (!$validator->validateUpdate((array) $request->input())) {
            return $this->json(['validation' => $validator->errors()], 'Datos inválidos. Por favor revisa los campos.', 422);
        }

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

    public function olvidar(Request $request, string $id): Response
    {
        $this->container->get(GdprService::class)->olvidar($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Los datos personales del cliente han sido anonimizados.');
    }

    public function exportarDatos(Request $request, string $id): Response
    {
        $result = $this->container->get(GdprService::class)->exportarDatos($this->firmaId(), (int) $id, $request);

        return $this->json($result, 'Exportación de datos generada correctamente.');
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

    /** @return array<string, list<array{codigo: string, etiqueta: string}>> */
    private function catalogos(): array
    {
        $firmaId = $this->firmaId();
        $catalogs = $this->container->get(CatalogoLookupService::class);

        return [
            'tipo_documento' => $catalogs->items($firmaId, 'tipo_documento'),
            'origen_fuente' => $catalogs->items($firmaId, 'origen_fuente'),
            'medio' => $catalogs->items($firmaId, 'medio'),
        ];
    }
}
