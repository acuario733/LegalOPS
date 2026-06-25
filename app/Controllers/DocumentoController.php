<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoService;
use App\Services\ClienteService;
use App\Services\DocumentoService;
use App\Services\DocumentoVersionService;
use App\Services\GastoService;

final class DocumentoController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('documentos/index', [
            'title' => 'Documentos',
            'documentos' => $this->container->get(DocumentoService::class)->list($firmaId, (array) $request->query(), max(1, (int) $request->query('page', 1))),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'gastos' => $this->container->get(GastoService::class)->list($firmaId, [], 1, 100)['items'],
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('documentos/show', [
            'title' => 'Ficha de documento',
            'documento' => $this->container->get(DocumentoService::class)->find($firmaId, (int) $id),
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 100)['items'],
            'gastos' => $this->container->get(GastoService::class)->list($firmaId, [], 1, 100)['items'],
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $file = $request->file('archivo');
        if (!is_array($file)) {
            throw new HttpException(422, 'Adjunte un archivo valido.');
        }
        $id = $this->container->get(DocumentoService::class)->create($this->firmaId(), (array) $request->input(), $file, $request);

        return $this->json(['id' => $id], 'Documento cargado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->container->get(DocumentoService::class)->update($this->firmaId(), (int) $id, (array) $request->input(), $request);

        return $this->json(null, 'Documento actualizado correctamente.');
    }

    public function delete(Request $request, string $id): Response
    {
        $this->container->get(DocumentoService::class)->delete($this->firmaId(), (int) $id, $request);

        return $this->json(null, 'Documento eliminado correctamente.');
    }

    public function version(Request $request, string $id): Response
    {
        $file = $request->file('archivo');
        if (!is_array($file)) {
            throw new HttpException(422, 'Adjunte un archivo valido.');
        }
        $versionId = $this->container->get(DocumentoVersionService::class)->create($this->firmaId(), (int) $id, $file, $request);

        return $this->json(['id' => $versionId], 'Version del documento creada correctamente.', 201);
    }

    public function download(Request $request, string $id): Response
    {
        return $this->container->get(DocumentoVersionService::class)->downloadCurrent($this->firmaId(), (int) $id, $request);
    }

    public function downloadVersion(Request $request, string $id, string $versionId): Response
    {
        return $this->container->get(DocumentoVersionService::class)->download($this->firmaId(), (int) $id, (int) $versionId, $request);
    }

    public function search(Request $request): Response
    {
        return $this->json($this->container->get(DocumentoService::class)->list($this->firmaId(), (array) $request->query(), max(1, (int) $request->query('page', 1))));
    }

    public function detail(Request $request, string $id): Response
    {
        return $this->json($this->container->get(DocumentoService::class)->find($this->firmaId(), (int) $id));
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
