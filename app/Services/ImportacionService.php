<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\ImportacionRepository;

final class ImportacionService
{
    /** @var array<string, list<string>> */
    private array $headers = [
        'clientes' => ['nombre_razon_social', 'tipo_persona', 'tipo_documento', 'numero_documento', 'email', 'telefono'],
        'casos' => ['cliente_id', 'titulo', 'radicado', 'prioridad', 'estado'],
    ];

    public function __construct(
        private readonly ImportacionRepository $repository,
        private readonly ClienteRepository $clienteRepository,
        private readonly CasoRepository $casoRepository,
        private readonly ClienteService $clientes,
        private readonly CasoService $casos,
        private readonly Database $database,
        private readonly LimitePlanService $limits,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $firmaId): array
    {
        return $this->repository->recent($firmaId);
    }

    public function cleanupExpired(): int
    {
        return $this->repository->cleanupExpired($this->storageRoot());
    }

    /** @param array<string, mixed> $file @return array<string, mixed> */
    public function preview(int $firmaId, string $type, array $file, Request $request): array
    {
        if (!isset($this->headers[$type])) {
            throw new HttpException(422, 'Tipo de importacion no valido.');
        }
        $this->limits->requireCapacity($firmaId, 'importaciones', $request);
        $metadata = $this->validateFile($file);
        $relative = 'imports/firma_' . $firmaId . '/' . date('Ymd_His') . '_' . $type . '_' . bin2hex(random_bytes(4)) . '.csv';
        $path = $this->storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'No fue posible preparar la importacion.');
        }
        $this->storeFile((string) $file['tmp_name'], $path);

        try {
            $preview = $this->parse($firmaId, $path, $type);
            $id = $this->repository->create([
                'firma_id' => $firmaId,
                'usuario_id' => $this->auth->id(),
                'tipo' => $type,
                'estado' => 'previsualizada',
                'archivo_path' => $relative,
                'nombre_original' => $metadata['name'],
                'filas_total' => $preview['total'],
                'filas_validas' => $preview['validas'],
                'filas_error' => count($preview['errores']),
                'errores_json' => json_encode($preview['errores'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'resultado_json' => null,
                'expires_at' => date('Y-m-d H:i:s', time() + 86400),
            ]);
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }
            throw $exception;
        }
        $this->audit->record('IMPORTACION_PREVISUALIZADA', 'importaciones', 'importacion', $id, [
            'tipo' => $type,
            'filas_total' => $preview['total'],
            'filas_error' => count($preview['errores']),
        ], $request, $firmaId);

        return ['id' => $id] + $preview;
    }

    /** @return array<string, mixed> */
    public function confirm(int $firmaId, int $id, Request $request): array
    {
        $import = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'La importacion no existe.');
        if ($import['estado'] !== 'previsualizada') {
            throw new HttpException(409, 'La importacion ya fue procesada.');
        }
        if ($import['expires_at'] !== null && strtotime((string) $import['expires_at']) < time()) {
            throw new HttpException(409, 'La importacion expiro.');
        }
        $path = $this->resolveStoredPath((string) $import['archivo_path']);
        $preview = $this->parse($firmaId, $path, (string) $import['tipo']);
        if ($preview['errores'] !== []) {
            throw new HttpException(422, 'La importacion tiene errores pendientes.', ['rows' => $preview['errores']]);
        }

        $created = $this->database->transaction(function () use ($firmaId, $import, $preview, $request): int {
            $created = 0;
            foreach ($preview['rows'] as $row) {
                if ($import['tipo'] === 'clientes') {
                    $this->clientes->create($firmaId, $row + ['tratamiento_datos_autorizado' => '1', 'autorizacion_medio' => 'importacion_csv'], $request);
                    $created++;
                } elseif ($import['tipo'] === 'casos') {
                    $this->casos->create($firmaId, $row, $request);
                    $created++;
                }
            }

            return $created;
        });

        $result = ['creados' => $created, 'tipo' => $import['tipo']];
        $this->repository->markConfirmed($firmaId, $id, $result);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->audit->record('IMPORTACION_REALIZADA', 'importaciones', 'importacion', $id, $result, $request, $firmaId, 'warning');

        return $result;
    }

    /** @return array{name: string} */
    private function validateFile(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['name'], $file['size'])) {
            throw new HttpException(422, 'El CSV no fue recibido correctamente.');
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > 2 * 1024 * 1024) {
            throw new HttpException(422, 'El CSV esta vacio o supera 2 MB.');
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new HttpException(422, 'Solo se aceptan archivos CSV.');
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
        if (!in_array($detected, ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream'], true)) {
            throw new HttpException(422, 'El MIME detectado no corresponde a un CSV permitido.');
        }

        return ['name' => mb_substr(basename((string) $file['name']), 0, 255)];
    }

    /** @return array{headers: list<string>, rows: list<array<string, string>>, errores: list<array<string, mixed>>, total: int, validas: int} */
    private function parse(int $firmaId, string $path, string $type): array
    {
        if (!is_file($path)) {
            throw new HttpException(404, 'El archivo temporal no esta disponible.');
        }
        $handle = fopen($path, 'rb');
        if (!is_resource($handle)) {
            throw new HttpException(409, 'No fue posible leer el CSV.');
        }
        $expected = $this->headers[$type];
        $headers = fgetcsv($handle);
        $headers = is_array($headers) ? array_map(static fn ($h): string => trim((string) $h), $headers) : [];
        if (isset($headers[0])) {
            $headers[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        if ($headers !== $expected) {
            fclose($handle);
            return ['headers' => $headers, 'rows' => [], 'errores' => [['fila' => 1, 'error' => 'Encabezados esperados: ' . implode(',', $expected)]], 'total' => 0, 'validas' => 0];
        }

        $rows = [];
        $errors = [];
        /** @var array<string, int> $seenDocuments */
        $seenDocuments = [];
        /** @var array<string, int> $seenRadicados */
        $seenRadicados = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($line > 501) {
                $errors[] = ['fila' => $line, 'error' => 'Limite de 500 filas por importacion.'];
                break;
            }
            if (count($values) !== count($headers)) {
                $errors[] = ['fila' => $line, 'error' => 'Cantidad de columnas invalida.'];
                continue;
            }
            $row = array_combine($headers, array_map(static fn ($v): string => trim((string) $v), $values));
            $missing = $type === 'clientes'
                ? trim($row['nombre_razon_social'] ?? '') === ''
                : (trim($row['cliente_id'] ?? '') === '' || trim($row['titulo'] ?? '') === '');
            if ($missing) {
                $errors[] = ['fila' => $line, 'error' => 'Campos obligatorios vacios.'];
                continue;
            }
            foreach ($this->rowErrors($firmaId, $type, $row, $line, $seenDocuments, $seenRadicados) as $error) {
                $errors[] = $error;
            }
            if ($this->rowHasErrors($errors, $line)) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows, 'errores' => $errors, 'total' => count($rows) + count($errors), 'validas' => count($rows)];
    }

    /**
     * @param array<string, string> $row
     * @param array<string, int> $seenDocuments
     * @param array<string, int> $seenRadicados
     * @param-out array<string, int> $seenDocuments
     * @param-out array<string, int> $seenRadicados
     * @return list<array<string, mixed>>
     */
    private function rowErrors(int $firmaId, string $type, array $row, int $line, array &$seenDocuments, array &$seenRadicados): array
    {
        if ($type === 'clientes') {
            // @phpstan-ignore paramOut.type
            return $this->clientRowErrors($firmaId, $row, $line, $seenDocuments);
        }
        if ($type === 'casos') {
            // @phpstan-ignore paramOut.type
            return $this->caseRowErrors($firmaId, $row, $line, $seenRadicados);
        }

        return [];
    }

    /** @param list<array<string, mixed>> $errors */
    private function rowHasErrors(array $errors, int $line): bool
    {
        foreach ($errors as $error) {
            if ((int) ($error['fila'] ?? 0) === $line) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $row @param array<string, int> $seenDocuments @param-out array<string, int> $seenDocuments @return list<array<string, mixed>> */
    private function clientRowErrors(int $firmaId, array $row, int $line, array &$seenDocuments): array
    {
        $errors = [];
        $document = $this->normalizeDocument((string) ($row['numero_documento'] ?? ''));
        if ($document === '') {
            return $errors;
        }
        if (isset($seenDocuments[$document])) {
            $errors[] = ['fila' => $line, 'error' => 'Documento duplicado en CSV; ya aparece en fila ' . $seenDocuments[$document] . '.'];
        } else {
            $seenDocuments[$document] = $line;
        }
        if ($this->clienteRepository->documentHashExists($firmaId, hash('sha256', $document))) {
            $errors[] = ['fila' => $line, 'error' => 'Ya existe un cliente activo con ese documento en la firma.'];
        }

        return $errors;
    }

    /** @param array<string, string> $row @param array<string, int> $seenRadicados @param-out array<string, int> $seenRadicados @return list<array<string, mixed>> */
    private function caseRowErrors(int $firmaId, array $row, int $line, array &$seenRadicados): array
    {
        $errors = [];
        $clienteId = filter_var($row['cliente_id'] ?? null, FILTER_VALIDATE_INT);
        if ($clienteId === false || $this->clienteRepository->findForFirma($firmaId, (int) $clienteId) === null) {
            $errors[] = ['fila' => $line, 'error' => 'El cliente indicado no existe en la firma.'];
        }
        $radicado = trim((string) ($row['radicado'] ?? ''));
        if ($radicado === '') {
            return $errors;
        }
        $radicadoKey = mb_strtolower($radicado);
        if (isset($seenRadicados[$radicadoKey])) {
            $errors[] = ['fila' => $line, 'error' => 'Radicado duplicado en CSV; ya aparece en fila ' . $seenRadicados[$radicadoKey] . '.'];
        } else {
            $seenRadicados[$radicadoKey] = $line;
        }
        if ($this->casoRepository->radicadoExists($firmaId, $radicado)) {
            $errors[] = ['fila' => $line, 'error' => 'Ya existe un caso activo con ese radicado en la firma.'];
        }

        return $errors;
    }

    private function normalizeDocument(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', mb_strtoupper($value)) ?? '';
    }

    private function storeFile(string $tmp, string $target): void
    {
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $target) : rename($tmp, $target);
        if (!$ok) {
            throw new HttpException(500, 'No fue posible almacenar temporalmente el CSV.');
        }
    }

    private function storageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }

    private function resolveStoredPath(string $relative): string
    {
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $relative) === 1) {
            throw new HttpException(404, 'El archivo temporal no esta disponible.');
        }
        $path = $this->storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $root = realpath($this->storageRoot());
        $real = realpath($path);
        if ($root === false || $real === false || !str_starts_with($real, $root)) {
            throw new HttpException(404, 'El archivo temporal no esta disponible.');
        }

        return $real;
    }
}
