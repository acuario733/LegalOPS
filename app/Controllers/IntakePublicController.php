<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\IntakeFormService;

final class IntakePublicController extends Controller
{
    public function show(Request $request, string $firmaSlug, string $formSlug): Response
    {
        $form = $this->container->get(IntakeFormService::class)->findPublic($firmaSlug, $formSlug);

        return $this->view('public/intake_form', [
            'title' => $form['titulo'],
            'form' => $form,
            'preview' => false,
            'csrfToken' => $this->csrf->token(),
            'publicScript' => null,
        ], 'public_form');
    }

    public function submit(Request $request, string $firmaSlug, string $formSlug): Response
    {
        $service = $this->container->get(IntakeFormService::class);
        $form = $service->findPublic($firmaSlug, $formSlug);
        $result = $service->processSubmission(
            (int) $form['id'],
            (array) $request->input(),
            $request->ip(),
            $request->userAgent()
        );

        return $this->json($result, $result['mensaje_exito'], 201);
    }
}
