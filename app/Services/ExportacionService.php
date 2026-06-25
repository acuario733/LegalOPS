<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ExportacionRepository;
use App\Repositories\ReporteRepository;

final class ExportacionService
{
    public function __construct(
        private readonly ReporteRepository $reports,
        private readonly ExportacionRepository $repository,
        private readonly ReporteService $reportService,
        private readonly LimitePlanService $limits,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @param array<string, mixed> $user @return array{path: string, name: string, rows: int, exportacion_id: int} */
    public function export(int $firmaId, string $type, array $user, Request $request): array
    {
        if (!$this->reportService->canExport($user, $type)) {
            throw new HttpException(403, 'No tiene permiso para exportar este reporte.');
        }
        $this->limits->requireCapacity($firmaId, 'exportaciones');
        $maxRows = 2000;
        $filters = $this->filters($request);
        $data = $this->reports->data($firmaId, $type, $maxRows + 1, $filters);
        if ($data['headers'] === []) {
            throw new HttpException(422, 'El reporte solicitado no existe.');
        }
        if (count($data['rows']) > $maxRows) {
            throw new HttpException(409, 'El reporte supera el limite de filas permitido. Ajuste los filtros antes de exportar.');
        }

        $relative = 'exports/firma_' . $firmaId . '/' . date('Ymd_His') . '_' . $type . '_' . bin2hex(random_bytes(4)) . '.csv';
        $path = $this->storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'No fue posible preparar la exportacion.');
        }
        $handle = fopen($path, 'wb');
        if (!is_resource($handle)) {
            throw new HttpException(500, 'No fue posible crear el archivo CSV.');
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            fputcsv($handle, array_map(fn (string $header): string => $this->csvValue($row[$header] ?? ''), $data['headers']));
        }
        fclose($handle);

        try {
            $id = $this->repository->create([
                'firma_id' => $firmaId,
                'usuario_id' => $this->auth->id(),
                'tipo' => $type,
                'filtros_json' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'estado' => 'generada',
                'archivo_path' => $relative,
                'filas' => count($data['rows']),
                'expires_at' => date('Y-m-d H:i:s', time() + 86400),
            ]);
            $this->audit->record('EXPORTACION_REALIZADA', 'reportes', 'exportacion', $id, [
                'tipo' => $type,
                'filas' => count($data['rows']),
                'filtros' => array_keys($filters),
            ], $request, $firmaId, 'warning');
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }
            throw $exception;
        }

        return ['path' => $path, 'name' => 'legalops_' . $type . '_' . date('Ymd_His') . '.csv', 'rows' => count($data['rows']), 'exportacion_id' => $id];
    }

    public function cleanupExpired(): int
    {
        return $this->repository->cleanupExpired($this->storageRoot());
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');
        $probe = ltrim($value);
        if ($probe !== '' && in_array($probe[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** @return array<string, string|int> */
    private function filters(Request $request): array
    {
        $allowed = ['cliente_id', 'estado', 'desde', 'hasta', 'modulo', 'accion'];
        $filters = [];
        foreach ($allowed as $key) {
            $value = $request->query($key);
            if (is_array($value) || $value === null || $value === '') {
                continue;
            }
            if ($key === 'cliente_id') {
                if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                    $filters[$key] = (int) $value;
                }
                continue;
            }
            $filters[$key] = mb_substr(trim((string) $value), 0, 120);
        }

        return $filters;
    }

    private function storageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }
}
