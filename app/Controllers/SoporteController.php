<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\SoporteService;

final class SoporteController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaScope = $this->permissions->allows('soporte.ver_firma', $this->currentUser());

        return $this->view('soporte/index', [
            'title' => 'Soporte',
            'tickets' => $this->container->get(SoporteService::class)->list($this->firmaId(), $firmaScope, max(1, (int) $request->query('page', 1))),
            'slaPolicy' => $this->container->get(SoporteService::class)->slaPolicy(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('soporte/show', [
            'title' => 'Ticket de soporte',
            'ticket' => $this->container->get(SoporteService::class)->find($this->firmaId(), (int) $id, $this->firmaScope()),
            'slaPolicy' => $this->container->get(SoporteService::class)->slaPolicy(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $id = $this->container->get(SoporteService::class)->create($this->firmaId(), (array) $request->input(), $request);

        return $this->json(['id' => $id], 'Ticket creado correctamente.', 201);
    }

    public function message(Request $request, string $id): Response
    {
        $messageId = $this->container->get(SoporteService::class)->addMessage($this->firmaId(), (int) $id, (array) $request->input(), $request, $this->firmaScope());

        return $this->json(['id' => $messageId], 'Mensaje agregado correctamente.', 201);
    }

    public function status(Request $request, string $id): Response
    {
        $this->container->get(SoporteService::class)->changeStatus($this->firmaId(), (int) $id, (array) $request->input(), $request, $this->firmaScope());

        return $this->json(null, 'Estado del ticket actualizado.');
    }

    public function global(Request $request): Response
    {
        return $this->view('superadmin/soporte/index', [
            'title' => 'Soporte global',
            'tickets' => $this->container->get(SoporteService::class)->globalQueue(),
            'slaPolicy' => $this->container->get(SoporteService::class)->slaPolicy(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function globalStatus(Request $request, string $firmaId, string $id): Response
    {
        $this->container->get(SoporteService::class)->changeStatus((int) $firmaId, (int) $id, (array) $request->input(), $request, true);

        return $this->json(null, 'Estado del ticket actualizado.');
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operacion requiere una firma activa.');
        }

        return (int) $firmaId;
    }

    private function firmaScope(): bool
    {
        return $this->permissions->allows('soporte.ver_firma', $this->currentUser());
    }
}
