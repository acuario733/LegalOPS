<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\DocumentTemplateService;
use App\Services\TemplateVariableService;

final class DocumentTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $service = $this->container->get(DocumentTemplateService::class);

        return $this->view('plantillas/index', [
            'title' => 'Plantillas documentales',
            'templates' => $service->list($firmaId, $this->nullableString($request->query('categoria'))),
            'categorias' => $service->categorias($firmaId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $template = $this->container->get(DocumentTemplateService::class)->create($this->firmaId(), (array) $request->input());

        return $this->json($template, 'Plantilla creada correctamente.', 201);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->form($this->container->get(DocumentTemplateService::class)->find((int) $id, $this->firmaId()));
    }

    public function update(Request $request, string $id): Response
    {
        $template = $this->container->get(DocumentTemplateService::class)->update((int) $id, $this->firmaId(), (array) $request->input());

        return $this->json($template, 'Plantilla actualizada correctamente.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->container->get(DocumentTemplateService::class)->delete((int) $id, $this->firmaId());

        return $this->json(null, 'Plantilla eliminada correctamente.');
    }

    public function variables(Request $request): Response
    {
        return $this->json($this->container->get(TemplateVariableService::class)->getAvailableVariables());
    }

    public function listForCase(Request $request): Response
    {
        $templates = array_values(array_filter(
            $this->container->get(DocumentTemplateService::class)->list($this->firmaId(), $this->nullableString($request->query('categoria'))),
            static fn (array $template): bool => (int) ($template['activo'] ?? 0) === 1
        ));

        return $this->json($templates);
    }

    public function generate(Request $request, string $id): Response
    {
        $casoId = filter_var($request->input('caso_id'), FILTER_VALIDATE_INT);
        if ($casoId === false) {
            throw new HttpException(422, 'Seleccione un caso valido.');
        }

        $result = $this->container->get(DocumentTemplateService::class)->generate(
            (int) $id,
            $this->firmaId(),
            (int) $casoId,
            $this->usuarioId(),
            (array) $request->input('custom_vars', []),
            $request
        );

        return $this->json($result, 'Documento generado correctamente.', 201);
    }

    /** @param array<string, mixed>|null $template */
    private function form(?array $template): Response
    {
        $firmaId = $this->firmaId();
        $service = $this->container->get(DocumentTemplateService::class);

        return $this->view('plantillas/form', [
            'title' => $template === null ? 'Nueva plantilla' : 'Editar plantilla',
            'template' => $template,
            'categorias' => $service->categorias($firmaId),
            'variables' => $this->container->get(TemplateVariableService::class)->getAvailableVariables(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }

    private function usuarioId(): int
    {
        $id = $this->auth->id();
        if ($id === null) {
            throw new HttpException(403, 'La operacion requiere usuario autenticado.');
        }

        return (int) $id;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
