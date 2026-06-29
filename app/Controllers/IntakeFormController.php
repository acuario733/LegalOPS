<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\IntakeFormService;

final class IntakeFormController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();

        return $this->view('intake/index', [
            'title' => 'Formularios de captación',
            'forms' => $this->container->get(IntakeFormService::class)->list($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function builder(Request $request, ?string $id = null): Response
    {
        $firmaId = $this->firmaId();
        $service = $this->container->get(IntakeFormService::class);
        $form = $id === null ? null : $service->find((int) $id, $firmaId);

        return $this->view('intake/form_builder', [
            'title' => $form === null ? 'Nuevo formulario' : 'Editar formulario',
            'form' => $form,
            'fieldTypes' => $service->getFieldTypes(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = (array) $request->input();
        $this->validateInput($data);
        $form = $this->container->get(IntakeFormService::class)->create($this->firmaId(), $data);

        return $this->json($form, 'Formulario creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $data = (array) $request->input();
        $this->validateInput($data);
        $form = $this->container->get(IntakeFormService::class)->update((int) $id, $this->firmaId(), $data);

        return $this->json($form, 'Formulario actualizado correctamente.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->container->get(IntakeFormService::class)->delete((int) $id, $this->firmaId());

        return $this->json(null, 'Formulario eliminado correctamente.');
    }

    public function submissions(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();
        $service = $this->container->get(IntakeFormService::class);
        $form = $service->find((int) $id, $firmaId);

        return $this->view('intake/submissions', [
            'title' => 'Envíos de ' . $form['nombre'],
            'form' => $form,
            'submissions' => $service->submissions((int) $id, $firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function preview(Request $request, string $id): Response
    {
        $form = $this->container->get(IntakeFormService::class)->find((int) $id, $this->firmaId());
        $currentUser = $this->currentUser();
        $form['firma_nombre'] = (string) ($currentUser['firma_nombre'] ?? 'LegalOPS Cloud');
        $form['firma_slug'] = (string) ($currentUser['firma_slug'] ?? '');

        return $this->view('public/intake_form', [
            'title' => 'Vista previa: ' . $form['titulo'],
            'form' => $form,
            'preview' => true,
            'csrfToken' => $this->csrf->token(),
            'publicScript' => null,
        ], 'public_form');
    }

    /** @param array<string, mixed> $data */
    private function validateInput(array $data): void
    {
        if (trim((string) ($data['nombre'] ?? '')) === '' || trim((string) ($data['titulo'] ?? '')) === '') {
            throw new HttpException(422, 'Nombre y título son obligatorios.');
        }
        if (!isset($data['campos']) || (!is_array($data['campos']) && !is_string($data['campos']))) {
            throw new HttpException(422, 'La configuración de campos es obligatoria.');
        }
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operación requiere una firma activa.');
        }

        return (int) $firmaId;
    }
}
