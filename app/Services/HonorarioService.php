<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\HonorarioRepository;
use App\Validators\HonorarioValidator;

final class HonorarioService
{
    public function __construct(
        private readonly HonorarioRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos,
        private readonly HonorarioValidator $validator,
        private readonly LimitePlanService $limits,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
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
        return $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El honorario no existe en la firma.');
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'honorarios');
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data));
        $this->validate($normalized);
        $id = $this->repository->create($this->recordData($normalized));
        $this->audit->record('HONORARIO_CREADO', 'finanzas', 'honorario', $id, [
            'cliente_id' => $normalized['cliente_id'],
            'caso_id' => $normalized['caso_id'],
            'monto' => $normalized['monto'],
            'moneda' => $normalized['moneda'],
        ], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data, $before));
        $this->validate($normalized);
        $this->repository->update($firmaId, $id, $this->recordData($normalized));
        $this->audit->record('HONORARIO_MODIFICADO', 'finanzas', 'honorario', $id, [
            'anterior' => ['monto' => $before['monto'], 'estado' => $before['estado']],
            'nuevo' => ['monto' => $normalized['monto'], 'estado' => $normalized['estado']],
        ], $request, $firmaId);
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if ((float) $data['monto'] <= 0) {
            throw new HttpException(422, 'El monto del honorario debe ser mayor a cero.');
        }
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos del honorario.', $this->validator->errors());
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
            'concepto' => mb_substr($concept, 0, 180),
            'concepto_normalizado' => $this->normalizeText($concept, 180),
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($before['descripcion'] ?? null), 2000),
            'monto' => $this->money($data['monto'] ?? ($before['monto'] ?? 0)),
            'moneda' => strtoupper(mb_substr(trim((string) ($data['moneda'] ?? ($before['moneda'] ?? 'COP'))), 0, 3)),
            'fecha_acuerdo' => $this->dateValue($data['fecha_acuerdo'] ?? ($before['fecha_acuerdo'] ?? date('Y-m-d'))),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'pendiente')),
            'created_by_usuario_id' => $before['created_by_usuario_id'] ?? $this->auth->id(),
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
            'descripcion' => $data['descripcion'],
            'monto' => $data['monto'],
            'moneda' => $data['moneda'],
            'fecha_acuerdo' => $data['fecha_acuerdo'],
            'estado' => $data['estado'],
            'created_by_usuario_id' => $data['created_by_usuario_id'],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => $this->normalizeText((string) ($filters['q'] ?? ''), 180),
            'cliente_id' => $this->nullableInt($filters['cliente_id'] ?? null),
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'estado' => in_array(($filters['estado'] ?? ''), ['pendiente', 'parcial', 'pagado', 'cancelado'], true) ? $filters['estado'] : '',
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
}
