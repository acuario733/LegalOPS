<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PortalClienteRepository;

final class PortalClienteService
{
    public function __construct(
        private readonly PortalClienteRepository $repository,
        private readonly DocumentoVersionService $documents,
        private readonly AceptacionLegalService $legal,
        private readonly Auth $auth
    ) {
    }

    /** @return array<string, mixed> */
    public function home(int $firmaId, Request $request): array
    {
        $client = $this->client($firmaId);
        $this->log($firmaId, (int) $client['id'], 'PORTAL_ACCESO', null, null, $request);

        return [
            'cliente' => $client,
            'casos' => $this->repository->cases($firmaId, (int) $client['id']),
            'documentos' => $this->repository->documents($firmaId, (int) $client['id']),
            'finanzas' => $this->repository->finances($firmaId, (int) $client['id']),
            'pendientes_legales' => $this->legal->pending(),
        ];
    }

    public function downloadDocument(int $firmaId, int $documentId, Request $request): Response
    {
        $client = $this->client($firmaId);
        $this->repository->authorizedDocument($firmaId, (int) $client['id'], $documentId)
            ?? throw new HttpException(404, 'El documento no esta disponible en el portal.');
        $this->log($firmaId, (int) $client['id'], 'PORTAL_DESCARGA_DOCUMENTO', 'documento', $documentId, $request);

        return $this->documents->downloadCurrent($firmaId, $documentId, $request);
    }

    public function acceptLegal(int $firmaId, int $documentId, Request $request): void
    {
        $client = $this->client($firmaId);
        $this->legal->accept($documentId, $request);
        $this->log($firmaId, (int) $client['id'], 'PORTAL_ACEPTACION_LEGAL', 'documento_legal', $documentId, $request);
    }

    /** @return array<string, mixed> */
    private function client(int $firmaId): array
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            throw new HttpException(403, 'La operacion requiere usuario autenticado.');
        }

        return $this->repository->clientForUser($firmaId, (int) $userId) ?? throw new HttpException(403, 'El usuario no tiene un cliente activo asociado al portal.');
    }

    private function log(int $firmaId, int $clienteId, string $action, ?string $entityType, ?int $entityId, Request $request): void
    {
        $this->repository->logAccess($firmaId, (int) $this->auth->id(), $clienteId, $action, $entityType, $entityId, $request->ip(), $request->userAgent());
    }
}
