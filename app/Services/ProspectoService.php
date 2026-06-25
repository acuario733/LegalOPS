<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ClienteRepository;
use App\Repositories\ProspectoRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\ProspectoValidator;

final class ProspectoService
{
    public function __construct(
        private readonly ProspectoRepository $repository,
        private readonly ClienteRepository $clientes,
        private readonly UsuarioRepository $usuarios,
        private readonly ProspectoValidator $validator,
        private readonly ClienteService $clienteService,
        private readonly Database $database,
        private readonly AuditoriaService $audit
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
        return $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El prospecto no existe en la firma.');
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $normalized = $this->normalize($firmaId, $data);
        $this->validateResponsible($firmaId, $normalized['responsable_usuario_id']);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del prospecto.', $this->validator->errors());
        }
        $id = $this->repository->create($this->recordData($normalized));
        $this->audit->record('PROSPECTO_CREADO', 'prospectos', 'prospecto', $id, [
            'estado' => $normalized['estado'],
            'responsable_usuario_id' => $normalized['responsable_usuario_id'],
        ], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->normalize($firmaId, $data, $before);
        $this->validateResponsible($firmaId, $normalized['responsable_usuario_id']);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos del prospecto.', $this->validator->errors());
        }
        $this->repository->update($firmaId, $id, $this->recordData($normalized));
        $this->audit->record('PROSPECTO_MODIFICADO', 'prospectos', 'prospecto', $id, [
            'anterior' => ['estado' => $before['estado'], 'responsable_usuario_id' => $before['responsable_usuario_id']],
            'nuevo' => ['estado' => $normalized['estado'], 'responsable_usuario_id' => $normalized['responsable_usuario_id']],
        ], $request, $firmaId);
    }

    public function setStatus(int $firmaId, int $id, string $status, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $data = ['estado' => $status];
        if (!$this->validator->validateStatus($data)) {
            throw new HttpException(422, 'El estado seleccionado no es valido.', $this->validator->errors());
        }
        $this->repository->setStatus($firmaId, $id, $status);
        $this->audit->record('PROSPECTO_MODIFICADO', 'prospectos', 'prospecto', $id, [
            'anterior' => ['estado' => $before['estado']],
            'nuevo' => ['estado' => $status],
        ], $request, $firmaId);
    }

    public function convert(int $firmaId, int $id, Request $request): int
    {
        return $this->database->transaction(function () use ($firmaId, $id, $request): int {
            $prospect = $this->repository->findForUpdate($firmaId, $id) ?? throw new HttpException(404, 'El prospecto no existe en la firma.');
            if ($prospect['converted_cliente_id'] !== null) {
                return (int) $prospect['converted_cliente_id'];
            }

            $existing = null;
            if ($prospect['documento_hash'] !== null) {
                $existing = $this->clientes->findByDocumentHash($firmaId, (string) $prospect['documento_hash']);
            }
            $clienteId = $existing === null
                ? $this->clienteService->create($firmaId, $this->clientPayload($prospect), $request)
                : (int) $existing['id'];

            $this->repository->markConverted($firmaId, $id, $clienteId);
            $this->audit->record('PROSPECTO_CONVERTIDO', 'prospectos', 'prospecto', $id, [
                'cliente_id' => $clienteId,
                'modo' => $existing === null ? 'creado' : 'vinculado',
            ], $request, $firmaId);

            return $clienteId;
        });
    }

    /** @param array<string, mixed> $prospect @return array<string, mixed> */
    private function clientPayload(array $prospect): array
    {
        return [
            'tipo_persona' => $prospect['tipo_persona'],
            'nombre_razon_social' => $prospect['nombre'],
            'tipo_documento' => $prospect['tipo_documento'],
            'numero_documento' => $prospect['numero_documento'],
            'email' => $prospect['email'],
            'telefono' => $prospect['telefono'],
            'estado' => 'activo',
            'origen' => 'prospecto',
            'observaciones' => $prospect['notas'],
            'tratamiento_datos_autorizado' => (int) $prospect['tratamiento_datos_autorizado'] === 1 ? '1' : '0',
            'autorizacion_medio' => 'prospecto',
            'autorizacion_version' => 'operacion-juridica-v1',
        ];
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $document = trim((string) ($data['numero_documento'] ?? ($before['numero_documento'] ?? '')));
        $documentNormalized = $this->normalizeDocument($document);
        $name = trim((string) ($data['nombre'] ?? ($before['nombre'] ?? '')));
        $company = trim((string) ($data['empresa'] ?? ($before['empresa'] ?? '')));

        return [
            'firma_id' => $firmaId,
            'nombre' => mb_substr($name, 0, 180),
            'nombre_normalizado' => $this->normalizeText($name, 180),
            'tipo_persona' => (string) ($data['tipo_persona'] ?? ($before['tipo_persona'] ?? 'natural')),
            'email' => $this->nullableString($data['email'] ?? ($before['email'] ?? null), 254, true),
            'telefono' => $this->nullableString($data['telefono'] ?? ($before['telefono'] ?? null), 60),
            'tipo_documento' => $this->nullableString($data['tipo_documento'] ?? ($before['tipo_documento'] ?? null), 40),
            'numero_documento' => $this->nullableString($document, 80),
            'documento_normalizado' => $documentNormalized === '' ? null : $documentNormalized,
            'documento_hash' => $documentNormalized === '' ? null : hash('sha256', $documentNormalized),
            'empresa' => $this->nullableString($company, 180),
            'empresa_normalizada' => $company === '' ? null : $this->normalizeText($company, 180),
            'fuente' => $this->nullableString($data['fuente'] ?? ($before['fuente'] ?? null), 120),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'nuevo')),
            'responsable_usuario_id' => $this->nullableInt($data['responsable_usuario_id'] ?? ($before['responsable_usuario_id'] ?? null)),
            'valor_estimado' => $this->nullableDecimal($data['valor_estimado'] ?? ($before['valor_estimado'] ?? null)),
            'notas' => $this->nullableString($data['notas'] ?? ($before['notas'] ?? null), 1000),
            'tratamiento_datos_autorizado' => $this->truthy($data['tratamiento_datos_autorizado'] ?? ($before['tratamiento_datos_autorizado'] ?? 0)) ? 1 : 0,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'nombre' => $data['nombre'],
            'nombre_normalizado' => $data['nombre_normalizado'],
            'tipo_persona' => $data['tipo_persona'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'tipo_documento' => $data['tipo_documento'],
            'numero_documento' => $data['numero_documento'],
            'documento_normalizado' => $data['documento_normalizado'],
            'documento_hash' => $data['documento_hash'],
            'empresa' => $data['empresa'],
            'empresa_normalizada' => $data['empresa_normalizada'],
            'fuente' => $data['fuente'],
            'estado' => $data['estado'],
            'responsable_usuario_id' => $data['responsable_usuario_id'],
            'valor_estimado' => $data['valor_estimado'],
            'notas' => $data['notas'],
            'tratamiento_datos_autorizado' => $data['tratamiento_datos_autorizado'],
        ];
    }

    private function validateResponsible(int $firmaId, ?int $userId): void
    {
        if ($userId !== null && $this->usuarios->findForFirma($firmaId, $userId) === null) {
            throw new HttpException(422, 'El responsable no pertenece a la firma.');
        }
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $document = $this->normalizeDocument((string) ($filters['q'] ?? ''));

        return [
            'q' => $this->normalizeText((string) ($filters['q'] ?? ''), 180),
            'documento_query' => $document,
            'estado' => in_array(($filters['estado'] ?? ''), ['nuevo', 'contactado', 'consulta', 'cotizacion', 'negociacion', 'ganado', 'perdido'], true) ? $filters['estado'] : '',
        ];
    }

    private function normalizeText(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';

        return mb_substr($value, 0, $max);
    }

    private function normalizeDocument(string $value): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9]+/', '', strtoupper($value)) ?? '', 0, 80);
    }

    private function nullableString(mixed $value, int $max, bool $lower = false): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($lower ? strtolower($value) : $value, 0, $max);
    }

    private function nullableInt(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'si', 'yes'], true);
    }
}
