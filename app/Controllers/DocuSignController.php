<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\DocuSignService;

final class DocuSignController extends Controller
{
    public function connect(Request $request): Response
    {
        return Response::redirect($this->service()->startOAuth($this->firmaId(), $this->userId()));
    }

    public function callback(Request $request): Response
    {
        $this->service()->handleCallback($this->firmaId(), $this->userId(), (string) $request->query('code', ''), (string) $request->query('state', ''));

        return Response::redirect('/documentos');
    }

    public function send(Request $request, string $id): Response
    {
        $envelopeId = $this->service()->createEnvelope($this->firmaId(), (int) $id, (array) $request->input('firmantes', []));

        return $this->json(['envelope_id' => $envelopeId], 'Documento enviado a firma.');
    }

    public function status(Request $request, string $id): Response
    {
        return $this->json($this->service()->status($this->firmaId(), (int) $id));
    }

    public function webhook(Request $request): Response
    {
        $this->service()->handleWebhook(
            $request->rawBody(),
            (string) $request->header('x-docusign-signature-1', ''),
            $request
        );

        return Response::json(['received' => true]);
    }

    private function service(): DocuSignService
    {
        return $this->container->get(DocuSignService::class);
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
