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
use App\Services\GastoService;
use App\Services\HonorarioService;
use App\Services\PagoService;
use App\Services\PortalAutorizacionService;
use App\Services\PortalAuthService;
use App\Services\UsuarioService;

final class PortalAutorizacionController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $clienteId = $this->nullableInt($request->query('cliente_id'));

        return $this->view('portal_autorizaciones/index', [
            'title' => 'Autorizaciones de portal',
            'clientes' => $this->container->get(ClienteService::class)->list($firmaId, [], 1, 100)['items'],
            'casos' => $this->container->get(CasoService::class)->list($firmaId, [], 1, 200)['items'],
            'documentos' => $this->container->get(DocumentoService::class)->list($firmaId, [], 1, 200)['items'],
            'honorarios' => $this->container->get(HonorarioService::class)->list($firmaId, [], 1, 200)['items'],
            'pagos' => $this->container->get(PagoService::class)->list($firmaId, [], 1, 200)['items'],
            'gastos' => $this->container->get(GastoService::class)->list($firmaId, [], 1, 200)['items'],
            'usuariosExternos' => array_values(array_filter($this->container->get(UsuarioService::class)->all($firmaId), static fn (array $user): bool => ($user['tipo'] ?? '') === 'cliente_externo')),
            'autorizaciones' => $clienteId === null ? [] : $this->container->get(PortalAutorizacionService::class)->overview($firmaId, $clienteId),
            'filters' => (array) $request->query(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function change(Request $request): Response
    {
        $this->container->get(PortalAutorizacionService::class)->change($this->firmaId(), (array) $request->input(), $request);

        return $this->json(null, 'Autorizacion de portal actualizada.');
    }

    public function search(Request $request): Response
    {
        $clienteId = $this->nullableInt($request->query('cliente_id'));

        return $this->json($clienteId === null ? [] : $this->container->get(PortalAutorizacionService::class)->overview($this->firmaId(), $clienteId));
    }

    public function invite(Request $request, string $id): Response
    {
        $this->container->get(PortalAuthService::class)->invite($this->firmaId(), (int) $id);

        return $this->json(null, 'Invitacion al portal encolada.');
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
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
