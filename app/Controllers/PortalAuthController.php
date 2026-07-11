<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PortalAuthService;

final class PortalAuthController extends Controller
{
    public function showActivation(Request $request, string $token): Response
    {
        try {
            $credential = $this->service()->activation($token);
            return Response::html($this->views->render('portal/activate', [
                'credential' => $credential,
                'token' => $token,
                'csrfToken' => $this->csrf->token(),
            ], null));
        } catch (HttpException $exception) {
            return Response::html('<h1>Activacion no disponible</h1><p>' . htmlspecialchars($exception->getMessage()) . '</p>', $exception->status());
        }
    }

    public function activate(Request $request, string $token): Response
    {
        $this->service()->activate($token, (string) $request->input('password', ''));

        return Response::redirect('/portal/login', 303);
    }

    public function showLogin(Request $request): Response
    {
        return Response::html($this->views->render('portal/login', ['csrfToken' => $this->csrf->token()], null));
    }

    public function login(Request $request): Response
    {
        $this->service()->login((string) $request->input('email', ''), (string) $request->input('password', ''));

        return Response::redirect('/portal', 303);
    }

    private function service(): PortalAuthService
    {
        return $this->container->get(PortalAuthService::class);
    }
}
