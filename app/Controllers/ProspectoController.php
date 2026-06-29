<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogoLookupService;
use App\Services\ProspectoService;
use App\Services\UsuarioService;
use App\Validators\ProspectoValidator;

final class ProspectoController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('prospectos/index', [
            'title' => 'Prospectos',
            'prospectos' => $this->container->get(ProspectoService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'catalogos' => $this->catalogos($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('prospectos/show', [
            'title' => 'Ficha de prospecto',
            'prospecto' => $this->container->get(ProspectoService::class)->find($firmaId, (int) $id),
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'catalogos' => $this->catalogos($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = new ProspectoValidator();
        if (!$validator->validateData((array) $request->input())) {
            return $this->json(['validation' => $validator->errors()], 'Datos inválidos. Por favor revisa los campos.', 422);
        }

        $id = $this->container->get(ProspectoService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Prospecto creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $validator = new ProspectoValidator();
        if (!$validator->validateData((array) $request->input())) {
            return $this->json(['validation' => $validator->errors()], 'Datos inválidos. Por favor revisa los campos.', 422);
        }

        $this->container->get(ProspectoService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Prospecto actualizado correctamente.');
    }

    public function status(Request $request, string $id): Response
    {
        $this->container->get(ProspectoService::class)->setStatus($this->firmaId(), (int) $id, (string) $request->input('estado', ''), $request);

        return $this->json(null, 'Estado actualizado correctamente.');
    }

    public function convert(Request $request, string $id): Response
    {
        $clienteId = $this->container->get(ProspectoService::class)->convert($this->firmaId(), (int) $id, $request);

        return $this->json(['cliente_id' => $clienteId], 'Prospecto convertido correctamente.');
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(ProspectoService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
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
    private function catalogos(int $firmaId): array
    {
        $catalogs = $this->container->get(CatalogoLookupService::class);

        return [
            'tipo_documento' => $catalogs->items($firmaId, 'tipo_documento'),
            'origen_fuente' => $catalogs->items($firmaId, 'origen_fuente'),
        ];
    }
}
