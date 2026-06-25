<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['title' => 'Iniciar sesión', 'csrfToken' => $this->csrf->token()], 'auth');
    }

    public function login(Request $request): Response
    {
        $user = $this->container->get(AuthService::class)->login((array) $request->input(), $request);
        $redirect = $user['tipo'] === 'cliente_externo' ? '/portal' : ($user['tipo'] === 'superadmin' ? '/superadmin/firmas' : '/health');

        return $this->json(['redirect' => $redirect], 'Sesión iniciada correctamente.');
    }

    public function logout(Request $request): Response
    {
        $this->container->get(AuthService::class)->logout($request);

        return $this->json(['redirect' => '/login'], 'Sesión cerrada correctamente.');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot-password', ['title' => 'Recuperar contraseña', 'csrfToken' => $this->csrf->token()], 'auth');
    }

    public function forgot(Request $request): Response
    {
        $this->container->get(AuthService::class)->requestPasswordReset(
            (string) $request->input('email', ''),
            ($firma = trim((string) $request->input('firma', ''))) === '' ? null : $firma,
            $request
        );

        return $this->json(null, 'Si los datos coinciden, se enviaron instrucciones de recuperación.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return $this->view('auth/reset-password', ['title' => 'Nueva contraseña', 'token' => $token, 'csrfToken' => $this->csrf->token()], 'auth');
    }

    public function reset(Request $request, string $token): Response
    {
        $this->container->get(AuthService::class)->resetPassword($token, (string) $request->input('password', ''), $request);

        return $this->json(['redirect' => '/login'], 'Contraseña actualizada correctamente.');
    }
}

