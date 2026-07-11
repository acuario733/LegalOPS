<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\GoogleCalendarService;

final class GoogleCalendarController extends Controller
{
    public function connect(Request $request): Response
    {
        return Response::redirect($this->service()->startOAuth($this->firmaId(), $this->userId()));
    }

    public function callback(Request $request): Response
    {
        $this->service()->handleCallback(
            $this->firmaId(),
            $this->userId(),
            (string) $request->query('code', ''),
            (string) $request->query('state', '')
        );

        return Response::redirect('/calendario');
    }

    public function sync(Request $request): Response
    {
        return $this->json($this->service()->syncEvents($this->firmaId(), $this->userId()), 'Calendario sincronizado.');
    }

    public function disconnect(Request $request): Response
    {
        $this->service()->disconnect($this->firmaId(), $this->userId());

        return $this->json(null, 'Google Calendar desconectado.');
    }

    private function service(): GoogleCalendarService
    {
        return $this->container->get(GoogleCalendarService::class);
    }

    private function firmaId(): int
    {
        return $this->currentFirma() === null ? throw new HttpException(403, 'La operacion requiere una firma activa.') : (int) $this->currentFirma();
    }

    private function userId(): int
    {
        return $this->auth->id() === null ? throw new HttpException(403, 'La operacion requiere usuario autenticado.') : (int) $this->auth->id();
    }
}
