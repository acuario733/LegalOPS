<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DocumentoRepository;
use App\Repositories\DocumentoVersionRepository;
use App\Validators\DocumentoVersionValidator;
use RuntimeException;

final class DocumentoVersionService
{
    /** @var array<string, list<string>> */
    private array $allowed = [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    public function __construct(
        private readonly DocumentoVersionRepository $repository,
        private readonly DocumentoRepository $documentos,
        private readonly DocumentoVersionValidator $validator,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $firmaId, int $documentId): array
    {
        $this->document($firmaId, $documentId);

        return $this->repository->allForDocument($firmaId, $documentId);
    }

    /** @param array<string, mixed> $file */
    public function create(int $firmaId, int $documentId, array $file, Request $request, string $event = 'DOCUMENTO_VERSION_CREADA'): int
    {
        $document = $this->document($firmaId, $documentId);
        $metadata = $this->inspectFile($file);
        $stored = null;

        try {
            return $this->database->transaction(function () use ($firmaId, $documentId, $file, $metadata, $request, $event, &$stored): int {
                $version = $this->repository->nextNumber($firmaId, $documentId);
                $target = $this->targetPath($firmaId, $documentId, $version, $metadata['extension']);
                $this->storeFile((string) $file['tmp_name'], $target['absolute']);
                $stored = $target['absolute'];
                $id = $this->repository->create([
                    'firma_id' => $firmaId,
                    'documento_id' => $documentId,
                    'version_numero' => $version,
                    'nombre_original' => $metadata['nombre_original'],
                    'nombre_fisico' => $target['name'],
                    'extension' => $metadata['extension'],
                    'mime_declarado' => $metadata['mime_declarado'],
                    'mime_detectado' => $metadata['mime_detectado'],
                    'size_bytes' => $metadata['size_bytes'],
                    'checksum_sha256' => $metadata['checksum_sha256'],
                    'storage_path' => $target['relative'],
                    'uploaded_by_usuario_id' => $this->auth->id(),
                ]);
                $this->documentos->setCurrentVersion($firmaId, $documentId, $id);
                $this->audit->record($event, 'documentos', 'documento', $documentId, [
                    'version_id' => $id,
                    'version_numero' => $version,
                    'mime' => $metadata['mime_detectado'],
                    'size_bytes' => $metadata['size_bytes'],
                    'checksum_sha256' => $metadata['checksum_sha256'],
                ], $request, $firmaId);

                return $id;
            });
        } catch (\Throwable $exception) {
            if ($stored !== null && is_file($stored)) {
                @unlink($stored);
            }
            throw $exception;
        }
    }

    public function downloadCurrent(int $firmaId, int $documentId, Request $request): Response
    {
        $document = $this->document($firmaId, $documentId);
        $version = $this->repository->findCurrent($firmaId, $documentId) ?? throw new HttpException(404, 'El documento no tiene version disponible.');

        return $this->downloadVersion($firmaId, $document, $version, $request);
    }

    public function download(int $firmaId, int $documentId, int $versionId, Request $request): Response
    {
        $document = $this->document($firmaId, $documentId);
        $version = $this->repository->findForDocument($firmaId, $documentId, $versionId) ?? throw new HttpException(404, 'La version no existe en la firma.');

        return $this->downloadVersion($firmaId, $document, $version, $request);
    }

    /** @return array<string, mixed> */
    private function document(int $firmaId, int $documentId): array
    {
        return $this->documentos->findForFirma($firmaId, $documentId) ?? throw new HttpException(404, 'El documento no existe en la firma.');
    }

    /** @param array<string, mixed> $document @param array<string, mixed> $version */
    private function downloadVersion(int $firmaId, array $document, array $version, Request $request): Response
    {
        $path = $this->resolveStoragePath((string) $version['storage_path']);
        if (!is_file($path) || hash_file('sha256', $path) !== $version['checksum_sha256']) {
            throw new HttpException(409, 'La integridad del documento no pudo verificarse.');
        }
        $this->audit->record('DOCUMENTO_DESCARGADO', 'documentos', 'documento', (int) $document['id'], [
            'version_id' => (int) $version['id'],
            'version_numero' => (int) $version['version_numero'],
            'checksum_sha256' => $version['checksum_sha256'],
        ], $request, $firmaId, 'warning');

        return Response::download($path, (string) $version['nombre_original'], 'application/octet-stream');
    }

    /** @param array<string, mixed> $file @return array<string, mixed> */
    private function inspectFile(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['name'], $file['size'])) {
            throw new HttpException(422, 'El archivo no fue recibido correctamente.');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_file($tmp)) {
            throw new HttpException(422, 'El archivo temporal no existe.');
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > 15 * 1024 * 1024) {
            throw new HttpException(422, 'El archivo supera el tamano permitido o esta vacio.');
        }
        $original = basename((string) $file['name']);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset($this->allowed[$extension])) {
            throw new HttpException(422, 'La extension del archivo no esta permitida.');
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        if (!in_array($detected, $this->allowed[$extension], true)) {
            throw new HttpException(422, 'El MIME detectado no coincide con la lista permitida.');
        }
        $data = [
            'nombre_original' => mb_substr($original, 0, 255),
            'extension' => $extension,
            'mime_declarado' => mb_substr((string) ($file['type'] ?? ''), 0, 120) ?: null,
            'mime_detectado' => mb_substr($detected, 0, 120),
            'size_bytes' => $size,
            'checksum_sha256' => hash_file('sha256', $tmp),
        ];
        if (!$this->validator->validateMetadata($data)) {
            throw new HttpException(422, 'Revise la metadata del archivo.', $this->validator->errors());
        }

        return $data;
    }

    /** @return array{name: string, relative: string, absolute: string} */
    private function targetPath(int $firmaId, int $documentId, int $version, string $extension): array
    {
        $name = bin2hex(random_bytes(20)) . '.' . $extension;
        $relative = 'documents/firmas/' . $firmaId . '/' . $documentId . '/version_' . $version . '/' . $name;
        $absolute = $this->storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No fue posible preparar el almacenamiento de documentos.');
        }

        return ['name' => $name, 'relative' => $relative, 'absolute' => $absolute];
    }

    private function storeFile(string $tmp, string $target): void
    {
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $target) : rename($tmp, $target);
        if (!$ok) {
            throw new RuntimeException('No fue posible almacenar el documento.');
        }
    }

    private function resolveStoragePath(string $relative): string
    {
        if (str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $relative) === 1) {
            throw new HttpException(404, 'El archivo solicitado no esta disponible.');
        }
        $path = $this->storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $root = realpath($this->storageRoot());
        $real = realpath($path);
        if ($root === false || $real === false || !str_starts_with($real, $root)) {
            throw new HttpException(404, 'El archivo solicitado no esta disponible.');
        }

        return $real;
    }

    private function storageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }
}
