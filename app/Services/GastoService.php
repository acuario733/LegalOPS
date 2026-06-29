<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\DocumentoRepository;
use App\Repositories\GastoRepository;
use App\Validators\GastoValidator;

final class GastoService
{
    public function __construct(
        private readonly GastoRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos,
        private readonly DocumentoRepository $documentos,
        private readonly GastoValidator $validator,
        private readonly LimitePlanService $limits,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly CatalogoLookupService $catalogs
    ) {
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function list(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $result = $this->repository->paginate($firmaId, $this->normalizeFilters($filters), max(1, $page), min(100, max(1, $perPage)));
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        return $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El gasto no existe en la firma.');
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'gastos', $request);
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data));
        $this->validate($normalized);

        return $this->database->transaction(function () use ($firmaId, $normalized, $request): int {
            $id = $this->repository->create($this->recordData($normalized));
            if ($normalized['documento_id'] !== null) {
                $this->repository->attachSupport($firmaId, $id, (int) $normalized['documento_id']);
            }
            $this->audit->record('GASTO_REGISTRADO', 'finanzas', 'gasto', $id, [
                'cliente_id' => $normalized['cliente_id'],
                'caso_id' => $normalized['caso_id'],
                'documento_id' => $normalized['documento_id'],
                'monto' => $normalized['monto'],
                'moneda' => $normalized['moneda'],
            ], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data, $before));
        if ($before['estado'] !== 'anulado' && $normalized['estado'] === 'anulado') {
            throw new HttpException(422, 'Use el flujo de anulacion trazable para anular gastos.');
        }
        $this->validate($normalized);

        $this->database->transaction(function () use ($firmaId, $id, $before, $normalized, $request): void {
            $this->repository->update($firmaId, $id, $this->recordData($normalized));
            if ($normalized['documento_id'] !== null) {
                $this->repository->attachSupport($firmaId, $id, (int) $normalized['documento_id']);
            }
            $this->audit->record('GASTO_MODIFICADO', 'finanzas', 'gasto', $id, [
                'anterior' => ['monto' => $before['monto'], 'estado' => $before['estado']],
                'nuevo' => ['monto' => $normalized['monto'], 'estado' => $normalized['estado']],
                'documento_id' => $normalized['documento_id'],
            ], $request, $firmaId);
        });
    }

    /** @param array<string, mixed> $data */
    public function annul(int $firmaId, int $id, array $data, Request $request): void
    {
        $gasto = $this->find($firmaId, $id);
        if ($gasto['estado'] === 'anulado') {
            throw new HttpException(409, 'El gasto ya esta anulado.');
        }
        $reason = $this->requiredReason($data['motivo'] ?? null);

        $this->repository->updateStatus($firmaId, $id, 'anulado');
        $this->audit->record('GASTO_ANULADO', 'finanzas', 'gasto', $id, [
            'cliente_id' => $gasto['cliente_id'],
            'caso_id' => $gasto['caso_id'],
            'monto' => $gasto['monto'],
            'moneda' => $gasto['moneda'],
            'motivo' => $reason,
        ], $request, $firmaId, 'warning');
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if ((float) $data['monto'] <= 0) {
            throw new HttpException(422, 'El monto del gasto debe ser mayor a cero.');
        }
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del gasto.', $this->validator->errors());
        }
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $concept = trim((string) ($data['concepto'] ?? ($before['concepto'] ?? '')));

        return [
            'firma_id' => $firmaId,
            'cliente_id' => $this->requiredInt($data['cliente_id'] ?? ($before['cliente_id'] ?? null)),
            'caso_id' => $this->nullableInt($data['caso_id'] ?? ($before['caso_id'] ?? null)),
            'documento_id' => $this->nullableInt($data['documento_id'] ?? null),
            'concepto' => mb_substr($concept, 0, 180),
            'concepto_normalizado' => $this->normalizeText($concept, 180),
            'categoria' => $this->nullableString($data['categoria'] ?? ($before['categoria'] ?? null), 100),
            'monto' => $this->money($data['monto'] ?? ($before['monto'] ?? 0)),
            'moneda' => $this->catalogs->normalizeRequired($firmaId, 'moneda', $data['moneda'] ?? ($before['moneda'] ?? 'COP'), 'Moneda'),
            'fecha_gasto' => $this->dateValue($data['fecha_gasto'] ?? ($before['fecha_gasto'] ?? date('Y-m-d'))),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'registrado')),
            'observaciones' => $this->nullableString($data['observaciones'] ?? ($before['observaciones'] ?? null), 2000),
            'registrado_por_usuario_id' => $before['registrado_por_usuario_id'] ?? $this->auth->id(),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateRelations(int $firmaId, array $data): array
    {
        if ($this->clientes->findForFirma($firmaId, (int) $data['cliente_id']) === null) {
            throw new HttpException(422, 'El cliente seleccionado no pertenece a la firma.');
        }
        if ($data['caso_id'] !== null) {
            $case = $this->casos->findForFirma($firmaId, (int) $data['caso_id']) ?? throw new HttpException(422, 'El caso seleccionado no pertenece a la firma.');
            if ((int) $case['cliente_id'] !== (int) $data['cliente_id']) {
                throw new HttpException(422, 'El caso no pertenece al cliente seleccionado.');
            }
        }
        if ($data['documento_id'] !== null) {
            $document = $this->documentos->findForFirma($firmaId, (int) $data['documento_id']) ?? throw new HttpException(422, 'El soporte seleccionado no pertenece a la firma.');
            if (!empty($document['cliente_id']) && (int) $document['cliente_id'] !== (int) $data['cliente_id']) {
                throw new HttpException(422, 'El soporte no pertenece al cliente seleccionado.');
            }
            if ($data['caso_id'] !== null && !empty($document['caso_id']) && (int) $document['caso_id'] !== (int) $data['caso_id']) {
                throw new HttpException(422, 'El soporte no pertenece al caso seleccionado.');
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'cliente_id' => $data['cliente_id'],
            'caso_id' => $data['caso_id'],
            'concepto' => $data['concepto'],
            'concepto_normalizado' => $data['concepto_normalizado'],
            'categoria' => $data['categoria'],
            'monto' => $data['monto'],
            'moneda' => $data['moneda'],
            'fecha_gasto' => $data['fecha_gasto'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
            'registrado_por_usuario_id' => $data['registrado_por_usuario_id'],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => $this->normalizeText((string) ($filters['q'] ?? ''), 180),
            'cliente_id' => $this->nullableInt($filters['cliente_id'] ?? null),
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'estado' => in_array(($filters['estado'] ?? ''), ['registrado', 'anulado'], true) ? $filters['estado'] : '',
        ];
    }

    private function normalizeText(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';

        return mb_substr($value, 0, $max);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }

    private function requiredInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? 0 : (int) $value;
    }

    private function money(mixed $value): string
    {
        return number_format((float) str_replace(',', '.', (string) $value), 2, '.', '');
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? date('Y-m-d') : mb_substr($value, 0, 10);
    }

    private function requiredReason(mixed $value): string
    {
        $reason = trim((string) ($value ?? ''));
        if (mb_strlen($reason) < 5) {
            throw new HttpException(422, 'El motivo debe tener al menos 5 caracteres.');
        }

        return mb_substr($reason, 0, 500);
    }
}
