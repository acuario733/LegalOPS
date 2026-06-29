<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CasoRepository;
use App\Repositories\TareaRepository;
use App\Repositories\TimeEntryRepository;
use App\Repositories\UsuarioRepository;
use DateTimeImmutable;

final class TimeEntryService
{
    public function __construct(
        private readonly TimeEntryRepository $repository,
        private readonly CasoRepository $casos,
        private readonly TareaRepository $tareas,
        private readonly UsuarioRepository $usuarios
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $firmaId, array $filters): array
    {
        return $this->repository->findForFirma($firmaId, $filters);
    }

    /** @return list<array<string, mixed>> */
    public function listByCaso(int $casoId, int $firmaId): array
    {
        $this->requireCaso($casoId, $firmaId);

        return $this->repository->findByCaso($casoId, $firmaId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(int $firmaId, int $usuarioId, array $data): array
    {
        $usuario = $this->usuarios->findForFirma($firmaId, $usuarioId);
        if ($usuario === null) {
            throw new HttpException(422, 'El usuario no pertenece a la firma.');
        }

        $normalized = $this->normalize($data);
        if ($normalized['tarifa_hora'] === null && ($usuario['tarifa_hora'] ?? null) !== null) {
            $normalized['tarifa_hora'] = $this->normalizeRate($usuario['tarifa_hora']);
        }
        $this->validate($normalized, $firmaId);

        $id = $this->repository->create($normalized + [
            'firma_id' => $firmaId,
            'usuario_id' => $usuarioId,
        ]);

        return $this->repository->findById($id, $firmaId)
            ?? throw new HttpException(500, 'No fue posible recuperar el registro de tiempo creado.');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, int $firmaId, array $data): array
    {
        $current = $this->repository->findById($id, $firmaId)
            ?? throw new HttpException(404, 'El registro de tiempo no existe en la firma.');
        $normalized = $this->normalize($data, $current);
        $this->validate($normalized, $firmaId);
        $this->repository->update($id, $firmaId, $normalized);

        return $this->repository->findById($id, $firmaId)
            ?? throw new HttpException(404, 'El registro de tiempo no existe en la firma.');
    }

    public function delete(int $id, int $firmaId): void
    {
        if ($this->repository->findById($id, $firmaId) === null) {
            throw new HttpException(404, 'El registro de tiempo no existe en la firma.');
        }
        if (!$this->repository->softDelete($id, $firmaId)) {
            throw new HttpException(404, 'El registro de tiempo no existe en la firma.');
        }
    }

    /**
     * @return array{
     *     caso_id: int,
     *     total_minutos: int,
     *     total_horas: float,
     *     minutos_facturables: int,
     *     minutos_no_facturables: int,
     *     monto_estimado: float
     * }
     */
    public function resumenByCaso(int $casoId, int $firmaId): array
    {
        $entries = $this->listByCaso($casoId, $firmaId);
        $totalMinutes = $this->repository->sumMinutesByCaso($casoId, $firmaId);
        $billableMinutes = 0;
        $estimatedAmount = 0.0;

        foreach ($entries as $entry) {
            if ((int) $entry['es_facturable'] !== 1) {
                continue;
            }
            $minutes = (int) $entry['duracion_minutos'];
            $billableMinutes += $minutes;
            if ($entry['tarifa_hora'] !== null) {
                $estimatedAmount += ($minutes / 60) * (float) $entry['tarifa_hora'];
            }
        }

        return [
            'caso_id' => $casoId,
            'total_minutos' => $totalMinutes,
            'total_horas' => round($totalMinutes / 60, 2),
            'minutos_facturables' => $billableMinutes,
            'minutos_no_facturables' => $totalMinutes - $billableMinutes,
            'monto_estimado' => round($estimatedAmount, 2),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $current
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?array $current = null): array
    {
        $rate = array_key_exists('tarifa_hora', $data)
            ? $this->normalizeRate($data['tarifa_hora'])
            : $this->normalizeRate($current['tarifa_hora'] ?? null);

        return [
            'caso_id' => $this->normalizeInteger($data['caso_id'] ?? ($current['caso_id'] ?? null)),
            'tarea_id' => $this->normalizeNullableInteger($data['tarea_id'] ?? ($current['tarea_id'] ?? null)),
            'descripcion' => trim((string) ($data['descripcion'] ?? ($current['descripcion'] ?? ''))),
            'fecha' => trim((string) ($data['fecha'] ?? ($current['fecha'] ?? ''))),
            'duracion_minutos' => $this->normalizeInteger($data['duracion_minutos'] ?? ($current['duracion_minutos'] ?? null)),
            'tarifa_hora' => $rate,
            'es_facturable' => $this->normalizeBoolean($data['es_facturable'] ?? ($current['es_facturable'] ?? 1)),
        ];
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data, int $firmaId): void
    {
        if ($data['duracion_minutos'] < 1 || $data['duracion_minutos'] > 1440) {
            throw new HttpException(422, 'La duración debe estar entre 1 y 1440 minutos.');
        }
        if ($data['descripcion'] === '' || mb_strlen($data['descripcion']) > 500) {
            throw new HttpException(422, 'La descripción es obligatoria y no puede superar 500 caracteres.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['fecha']);
        if ($date === false || $date->format('Y-m-d') !== $data['fecha']) {
            throw new HttpException(422, 'La fecha debe tener el formato YYYY-MM-DD.');
        }
        if ($date > new DateTimeImmutable('today')) {
            throw new HttpException(422, 'La fecha no puede ser futura.');
        }
        if ($data['tarifa_hora'] !== null && ($data['tarifa_hora'] < 0 || $data['tarifa_hora'] > 99999999.99)) {
            throw new HttpException(422, 'La tarifa por hora no es válida.');
        }

        $this->requireCaso($data['caso_id'], $firmaId);
        if ($data['tarea_id'] !== null) {
            $tarea = $this->tareas->findForFirma($firmaId, $data['tarea_id']);
            if ($tarea === null) {
                throw new HttpException(422, 'La tarea no pertenece a la firma.');
            }
            if ((int) ($tarea['caso_id'] ?? 0) !== $data['caso_id']) {
                throw new HttpException(422, 'La tarea no pertenece al caso seleccionado.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function requireCaso(int $casoId, int $firmaId): array
    {
        return $this->casos->findForFirma($firmaId, $casoId)
            ?? throw new HttpException(422, 'El caso no pertenece a la firma.');
    }

    private function normalizeInteger(mixed $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? 0 : (int) $integer;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false || $integer < 1 ? null : (int) $integer;
    }

    private function normalizeRate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : -1.0;
    }

    private function normalizeBoolean(mixed $value): int
    {
        return match ($value) {
            true, 1, '1' => 1,
            false, 0, '0' => 0,
            default => throw new HttpException(422, 'El indicador facturable debe ser verdadero o falso.'),
        };
    }
}
