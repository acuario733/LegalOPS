<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\OnboardingService;

final class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('onboarding/index', [
            'title' => 'Onboarding',
            'onboarding' => $this->container->get(OnboardingService::class)->dashboard($this->firmaId()),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function refresh(Request $request): Response
    {
        $this->container->get(OnboardingService::class)->refresh($this->firmaId(), $request);

        return $this->json(null, 'Onboarding actualizado.');
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
