<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\AceptacionLegalRepository;
use App\Repositories\DocumentoLegalRepository;
use App\Validators\AceptacionLegalValidator;

final class AceptacionLegalService
{
    public function __construct(
        private readonly DocumentoLegalRepository $documents,
        private readonly AceptacionLegalRepository $acceptances,
        private readonly AceptacionLegalValidator $validator,
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function allDocuments(): array
    {
        return $this->documents->all();
    }

    /** @param array<string, mixed> $data */
    public function createDocument(array $data, Request $request): int
    {
        $normalized = [
            'tipo' => strtolower(trim((string) ($data['tipo'] ?? ''))),
            'version' => trim((string) ($data['version'] ?? '')),
            'titulo' => trim((string) ($data['titulo'] ?? '')),
            'contenido' => trim((string) ($data['contenido'] ?? '')),
        ];
        if (!$this->validator->validateDocument($normalized)) {
            throw new HttpException(422, 'Revise el documento legal.', $this->validator->errors());
        }
        if ($this->documents->typeVersionExists($normalized['tipo'], $normalized['version'])) {
            throw new HttpException(409, 'Ya existe esa versión para el tipo de documento indicado.');
        }
        $normalized['checksum'] = hash('sha256', $normalized['contenido']);
        $id = $this->documents->create($normalized);
        $this->audit->record('DOCUMENTO_LEGAL_CREADO', 'legal', 'documento_legal', $id, ['tipo' => $normalized['tipo'], 'version' => $normalized['version']], $request);

        return $id;
    }

    public function publish(int $id, Request $request): void
    {
        $document = $this->documents->find($id) ?? throw new HttpException(404, 'El documento legal no existe.');
        if ($document['estado'] !== 'borrador') {
            throw new HttpException(409, 'Solo puede publicarse un documento legal en borrador.');
        }
        $publisherId = (int) $this->auth->id();
        $this->database->transaction(function () use ($id, $document, $publisherId, $request): void {
            $this->documents->publish($id, (string) $document['tipo'], $publisherId);
            $this->audit->record('DOCUMENTO_LEGAL_PUBLICADO', 'legal', 'documento_legal', $id, ['tipo' => $document['tipo'], 'version' => $document['version']], $request);
        });
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        return $this->documents->pendingForUser((int) $this->auth->id());
    }

    public function accept(int $documentId, Request $request): void
    {
        $userId = (int) $this->auth->id();
        $document = $this->documents->find($documentId) ?? throw new HttpException(404, 'El documento legal no existe.');
        if ($document['estado'] !== 'vigente') {
            throw new HttpException(409, 'Solo puede aceptar la versión legal vigente.');
        }
        if ($this->acceptances->exists($userId, $documentId)) {
            return;
        }
        $firmaId = $this->auth->firmaId();
        $evidence = hash('sha256', implode('|', [$document['checksum'], $userId, $request->ip(), microtime(true), bin2hex(random_bytes(8))]));
        $acceptanceId = $this->acceptances->create($firmaId === null ? null : (int) $firmaId, $userId, $documentId, $request->ip(), $request->userAgent(), $evidence);
        $this->audit->record('DOCUMENTO_LEGAL_ACEPTADO', 'legal', 'aceptacion_legal', $acceptanceId, ['documento_id' => $documentId, 'version' => $document['version']], $request, $firmaId === null ? null : (int) $firmaId);
    }
}
