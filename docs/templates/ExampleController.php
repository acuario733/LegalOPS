<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * Template de Controller para el módulo ficticio "Documentos".
 *
 * Muestra el patrón estándar para todos los métodos CRUD en LegalOPS Cloud V2.
 * Copiar y adaptar al módulo real, reemplazando "Documento" por el nombre correcto.
 *
 * REGISTRAR EN RUTAS (routes/web.php):
 *   $router->group('', ['auth', 'firma', 'commercial'], function (Router $r) {
 *       $r->get('/documentos', [DocumentoController::class, 'index'], ['permission:documentos.ver']);
 *       $r->post('/documentos', [DocumentoController::class, 'store'], ['permission:documentos.crear']);
 *       $r->get('/documentos/{id}', [DocumentoController::class, 'show'], ['permission:documentos.ver']);
 *       $r->patch('/documentos/{id}', [DocumentoController::class, 'update'], ['permission:documentos.editar']);
 *       $r->delete('/documentos/{id}', [DocumentoController::class, 'destroy'], ['permission:documentos.eliminar']);
 *   });
 */
final class ExampleController extends Controller
{
    /**
     * GET /documentos — Listado paginado.
     */
    public function index(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma(); // TENANT FILTER: firma_id

        $page    = (int) $request->query('page', 1);
        $filtros = [
            'q'      => (string) $request->query('q', ''),
            'estado' => (string) $request->query('estado', ''),
        ];

        // El Repository filtra por firma_id internamente
        $resultado = $this->container->get(\App\Repositories\DocumentoRepository::class)
            ->paginate($firmaId, $filtros, $page);

        // Para requests AJAX/JSON
        if ($request->wantsJson()) {
            return $this->json($resultado, 'OK');
        }

        // Para renderizado HTML
        return $this->view('documentos/index', [
            'documentos' => $resultado['items'],
            'total'      => $resultado['total'],
            'page'       => $page,
        ]);
    }

    /**
     * GET /documentos/{id} — Detalle de un documento.
     */
    public function show(Request $request): Response
    {
        $firmaId    = (int) $this->currentFirma(); // TENANT FILTER
        $documentoId = (int) $request->route('id');

        $documento = $this->container->get(\App\Repositories\DocumentoRepository::class)
            ->find($firmaId, $documentoId);

        if ($documento === null) {
            // 404 en lugar de 403 para no revelar que el recurso existe
            return $this->json(null, 'Documento no encontrado', 404, ok: false);
        }

        return $this->json($documento, 'OK');
    }

    /**
     * GET /documentos/crear — Formulario de creación (solo web).
     */
    public function create(Request $request): Response
    {
        return $this->view('documentos/form', ['documento' => null, 'modo' => 'crear']);
    }

    /**
     * POST /documentos — Crear un documento.
     */
    public function store(Request $request): Response
    {
        $firmaId = (int) $this->currentFirma(); // TENANT FILTER

        $data = $request->input();

        // Validar datos
        $errors = $this->container->get(Validator::class)->validate($data, [
            'titulo'      => 'required|maxLength:255',
            'descripcion' => 'required',
            // agregar reglas según el módulo
        ]);

        if (!empty($errors)) {
            return $this->json(null, 'Los datos proporcionados no son válidos.', 422, $errors, ok: false);
        }

        // Delegar al Repository (que siempre recibe firmaId)
        $newId = $this->container->get(\App\Repositories\DocumentoRepository::class)
            ->create($firmaId, $data);

        return $this->json(['id' => $newId], 'Documento creado exitosamente.', 201);
    }

    /**
     * GET /documentos/{id}/editar — Formulario de edición (solo web).
     */
    public function edit(Request $request): Response
    {
        $firmaId     = (int) $this->currentFirma(); // TENANT FILTER
        $documentoId = (int) $request->route('id');

        $documento = $this->container->get(\App\Repositories\DocumentoRepository::class)
            ->find($firmaId, $documentoId);

        if ($documento === null) {
            return $this->redirect('/documentos');
        }

        return $this->view('documentos/form', ['documento' => $documento, 'modo' => 'editar']);
    }

    /**
     * PATCH /documentos/{id} — Actualizar un documento.
     */
    public function update(Request $request): Response
    {
        $firmaId     = (int) $this->currentFirma(); // TENANT FILTER
        $documentoId = (int) $request->route('id');

        $data   = $request->input();
        $errors = $this->container->get(Validator::class)->validate($data, [
            'titulo' => 'maxLength:255',
        ]);

        if (!empty($errors)) {
            return $this->json(null, 'Los datos proporcionados no son válidos.', 422, $errors, ok: false);
        }

        $updated = $this->container->get(\App\Repositories\DocumentoRepository::class)
            ->update($firmaId, $documentoId, $data);

        if (!$updated) {
            return $this->json(null, 'Documento no encontrado.', 404, ok: false);
        }

        return $this->json(null, 'Documento actualizado exitosamente.');
    }

    /**
     * DELETE /documentos/{id} — Eliminar (soft delete) un documento.
     */
    public function destroy(Request $request): Response
    {
        $firmaId     = (int) $this->currentFirma(); // TENANT FILTER
        $documentoId = (int) $request->route('id');

        $deleted = $this->container->get(\App\Repositories\DocumentoRepository::class)
            ->delete($firmaId, $documentoId);

        if (!$deleted) {
            return $this->json(null, 'Documento no encontrado.', 404, ok: false);
        }

        return $this->json(null, 'Documento eliminado exitosamente.');
    }
}
