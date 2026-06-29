<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\HonorarioRepository;
use App\Repositories\PagoRepository;
use App\Validators\PagoValidator;

final class PagoService
{
    public function __construct(
        private readonly PagoRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly CasoRepository $casos,
        private readonly HonorarioRepository $honorarios,
        private readonly PagoValidator $validator,
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
        $result['items'] = array_map(fn (array $payment): array => $this->masked($payment), $result['items']);
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        return $this->masked($this->raw($firmaId, $id));
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'pagos', $request);
        $normalized = $this->validateRelations($firmaId, $this->normalize($firmaId, $data));
        if ((float) $normalized['monto'] <= 0) {
            throw new HttpException(422, 'El monto del pago debe ser mayor a cero.');
        }
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del pago.', $this->validator->errors());
        }

        return $this->database->transaction(function () use ($firmaId, $normalized, $request): int {
            if ($this->repository->duplicateExists($this->recordData($normalized))) {
                throw new HttpException(409, 'Ya existe un pago registrado con los mismos datos principales.');
            }
            if ($normalized['honorario_id'] !== null) {
                $fee = $this->honorarios->findForFirma($firmaId, (int) $normalized['honorario_id']) ?? throw new HttpException(422, 'El honorario seleccionado no pertenece a la firma.');
                if ($fee['estado'] === 'cancelado') {
                    throw new HttpException(422, 'No se pueden registrar pagos sobre un honorario cancelado.');
                }
                $paid = $this->honorarios->totalPaid($firmaId, (int) $normalized['honorario_id']);
                $balance = max(0.0, (float) $fee['monto'] - $paid);
                if ((float) $normalized['monto'] > $balance + 0.00001) {
                    throw new HttpException(422, 'El pago supera el saldo pendiente del honorario.');
                }
            }
            $id = $this->repository->create($this->recordData($normalized));
            if ($normalized['honorario_id'] !== null) {
                $fee = $this->honorarios->findForFirma($firmaId, (int) $normalized['honorario_id']) ?? null;
                if ($fee !== null && $fee['estado'] !== 'cancelado') {
                    $paid = $this->honorarios->totalPaid($firmaId, (int) $normalized['honorario_id']);
                    $this->honorarios->updateStatus($firmaId, (int) $normalized['honorario_id'], $paid + 0.00001 >= (float) $fee['monto'] ? 'pagado' : 'parcial');
                }
            }
            $this->audit->record('PAGO_REGISTRADO', 'finanzas', 'pago', $id, [
                'cliente_id' => $normalized['cliente_id'],
                'caso_id' => $normalized['caso_id'],
                'honorario_id' => $normalized['honorario_id'],
                'monto' => $normalized['monto'],
                'referencia_hash' => $normalized['referencia_hash'],
            ], $request, $firmaId, 'warning');

            return $id;
        });
    }

    /** @return array{campo: string, etiqueta: string, valor: string} */
    public function revealReference(int $firmaId, int $id, Request $request): array
    {
        $payment = $this->raw($firmaId, $id);
        $reference = (string) ($payment['referencia'] ?? '');
        $this->audit->record('PAGO_REFERENCIA_REVELADA', 'finanzas', 'pago', $id, [
            'referencia_hash' => $payment['referencia_hash'] ?? null,
        ], $request, $firmaId, 'warning');

        return ['campo' => 'referencia', 'etiqueta' => 'Referencia', 'valor' => $reference];
    }

    /** @param array<string, mixed> $data */
    public function annul(int $firmaId, int $id, array $data, Request $request): void
    {
        $payment = $this->raw($firmaId, $id);
        if ($payment['estado'] === 'anulado') {
            throw new HttpException(409, 'El pago ya esta anulado.');
        }
        $reason = $this->requiredReason($data['motivo'] ?? null);

        $this->database->transaction(function () use ($firmaId, $id, $payment, $reason, $request): void {
            $this->repository->updateStatus($firmaId, $id, 'anulado');
            if ($payment['honorario_id'] !== null) {
                $fee = $this->honorarios->findForFirma($firmaId, (int) $payment['honorario_id']);
                if ($fee !== null && $fee['estado'] !== 'cancelado') {
                    $paid = $this->honorarios->totalPaid($firmaId, (int) $payment['honorario_id']);
                    $this->honorarios->updateStatus($firmaId, (int) $payment['honorario_id'], $paid <= 0.0 ? 'pendiente' : ($paid + 0.00001 >= (float) $fee['monto'] ? 'pagado' : 'parcial'));
                }
            }
            $this->audit->record('PAGO_ANULADO', 'finanzas', 'pago', $id, [
                'cliente_id' => $payment['cliente_id'],
                'caso_id' => $payment['caso_id'],
                'honorario_id' => $payment['honorario_id'],
                'monto' => $payment['monto'],
                'moneda' => $payment['moneda'],
                'referencia_hash' => $payment['referencia_hash'] ?? null,
                'motivo' => $reason,
            ], $request, $firmaId, 'warning');
        });
    }

    /** @return array<string, mixed> */
    private function raw(int $firmaId, int $id): array
    {
        return $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El pago no existe en la firma.');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(int $firmaId, array $data): array
    {
        $reference = $this->nullableString($data['referencia'] ?? null, 180);

        return [
            'firma_id' => $firmaId,
            'cliente_id' => $this->requiredInt($data['cliente_id'] ?? null),
            'caso_id' => $this->nullableInt($data['caso_id'] ?? null),
            'honorario_id' => $this->nullableInt($data['honorario_id'] ?? null),
            'fecha_pago' => $this->dateValue($data['fecha_pago'] ?? date('Y-m-d')),
            'monto' => $this->money($data['monto'] ?? 0),
            'moneda' => $this->catalogs->normalizeRequired($firmaId, 'moneda', $data['moneda'] ?? 'COP', 'Moneda'),
            'metodo_pago' => $this->catalogs->normalizeRequired($firmaId, 'metodo_pago', $data['metodo_pago'] ?? null, 'Metodo de pago'),
            'referencia' => $reference,
            'referencia_hash' => $reference === null ? null : hash('sha256', $reference),
            'estado' => (string) ($data['estado'] ?? 'registrado'),
            'observaciones' => $this->nullableString($data['observaciones'] ?? null, 2000),
            'registrado_por_usuario_id' => $this->auth->id(),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateRelations(int $firmaId, array $data): array
    {
        if ($data['honorario_id'] !== null) {
            $fee = $this->honorarios->findForFirma($firmaId, (int) $data['honorario_id']) ?? throw new HttpException(422, 'El honorario seleccionado no pertenece a la firma.');
            $data['cliente_id'] = (int) $fee['cliente_id'];
            $data['caso_id'] = $this->nullableInt($fee['caso_id'] ?? null);
            $data['moneda'] = (string) $fee['moneda'];
        }
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
            'honorario_id' => $data['honorario_id'],
            'fecha_pago' => $data['fecha_pago'],
            'monto' => $data['monto'],
            'moneda' => $data['moneda'],
            'metodo_pago' => $data['metodo_pago'],
            'referencia' => $data['referencia'],
            'referencia_hash' => $data['referencia_hash'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
            'registrado_por_usuario_id' => $data['registrado_por_usuario_id'],
        ];
    }

    /** @param array<string, mixed> $payment @return array<string, mixed> */
    private function masked(array $payment): array
    {
        $reference = (string) ($payment['referencia'] ?? '');
        $payment['referencia_masked'] = $reference === '' ? '' : $this->mask($reference);
        unset($payment['referencia']);

        return $payment;
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'cliente_id' => $this->nullableInt($filters['cliente_id'] ?? null),
            'caso_id' => $this->nullableInt($filters['caso_id'] ?? null),
            'honorario_id' => $this->nullableInt($filters['honorario_id'] ?? null),
            'estado' => in_array(($filters['estado'] ?? ''), ['registrado', 'anulado'], true) ? $filters['estado'] : '',
        ];
    }

    private function mask(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(4, $length - 4)) . mb_substr($value, -4);
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
