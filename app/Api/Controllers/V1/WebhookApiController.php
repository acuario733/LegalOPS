<?php

declare(strict_types=1);

namespace App\Api\Controllers\V1;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\WebhookService;

final class WebhookApiController extends Controller
{
    use RequiresApiScope;

    public function index(Request $request): Response
    {
        $this->requireApiScope($request, 'webhooks:read');

        return $this->json($this->service()->list($this->apiFirmaId($request)));
    }

    public function store(Request $request): Response
    {
        $this->requireApiScope($request, 'webhooks:write');
        $result = $this->service()->register(
            $this->apiFirmaId($request),
            (string) $request->input('url', ''),
            (array) $request->input('eventos', [])
        );

        return $this->json($result, 'Webhook creado. Guarde el secreto; no se mostrara nuevamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireApiScope($request, 'webhooks:write');
        $this->service()->update(
            $this->apiFirmaId($request),
            (int) $id,
            (string) $request->input('url', ''),
            (array) $request->input('eventos', []),
            filter_var($request->input('activo', true), FILTER_VALIDATE_BOOL)
        );

        return $this->json(null, 'Webhook actualizado.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requireApiScope($request, 'webhooks:write');
        $this->service()->delete($this->apiFirmaId($request), (int) $id);

        return $this->json(null, 'Webhook eliminado.');
    }

    public function deliveries(Request $request, string $id): Response
    {
        $this->requireApiScope($request, 'webhooks:read');

        return $this->json($this->service()->deliveries($this->apiFirmaId($request), (int) $id));
    }

    private function service(): WebhookService
    {
        return $this->container->get(WebhookService::class);
    }
}
