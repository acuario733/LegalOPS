<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Permission;
use App\Core\Request;
use App\Repositories\CasoParteRepository;
use App\Repositories\CasoRepository;
use App\Validators\CasoParteValidator;

final class CasoParteService
{
    /** @var array<string, string> */
    private array $sensitiveFields = [
        'numero_documento' => 'Documento',
        'email' => 'Correo',
        'telefono' => 'Telefono',
        'direccion' => 'Direccion',
    ];

    public function __construct(
        private readonly CasoParteRepository $repository,
        private readonly CasoRepository $casos,
        private readonly CasoParteValidator $validator,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly Permission $permissions,
        private readonly CatalogoLookupService $catalogs
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $firmaId, int $casoId): array
    {
        $this->ensureCase($firmaId, $casoId);

        return array_map(fn (array $row): array => $this->masked($row), $this->repository->allForCase($firmaId, $casoId));
    }

    /** @return array<string, mixed> */
    public function caseInfo(int $firmaId, int $casoId): array
    {
        return $this->ensureCase($firmaId, $casoId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, int $casoId, array $data, Request $request): int
    {
        $this->ensureCase($firmaId, $casoId);
        $normalized = $this->normalize($firmaId, $casoId, $data);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos de la parte.', $this->validator->errors());
        }
        $id = $this->repository->create($this->recordData($normalized));
        $this->audit->record('PARTE_CREADA', 'partes', 'caso_parte', $id, [
            'caso_id' => $casoId,
            'tipo_parte' => $normalized['tipo_parte'],
            'documento_hash' => $normalized['documento_hash'],
        ], $request, $firmaId);

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $casoId, int $id, array $data, Request $request): void
    {
        $before = $this->raw($firmaId, $casoId, $id);
        $normalized = $this->normalize($firmaId, $casoId, $data, $before);
        if (!$this->validator->validateData($normalized)) {
            throw new HttpException(422, 'Revise los datos de la parte.', $this->validator->errors());
        }
        $this->repository->update($firmaId, $casoId, $id, $this->recordData($normalized));
        $this->audit->record('PARTE_MODIFICADA', 'partes', 'caso_parte', $id, [
            'caso_id' => $casoId,
            'anterior' => ['tipo_parte' => $before['tipo_parte'], 'estado' => $before['estado']],
            'nuevo' => ['tipo_parte' => $normalized['tipo_parte'], 'estado' => $normalized['estado']],
        ], $request, $firmaId);
    }

    public function delete(int $firmaId, int $casoId, int $id, Request $request): void
    {
        $part = $this->raw($firmaId, $casoId, $id);
        $this->repository->softDelete($firmaId, $casoId, $id);
        $this->audit->record('PARTE_ELIMINADA', 'partes', 'caso_parte', $id, [
            'caso_id' => $casoId,
            'nombre' => $part['nombre'],
            'documento_hash' => $part['documento_hash'],
        ], $request, $firmaId, 'warning');
    }

    /** @return array{campo: string, etiqueta: string, valor: string} */
    public function reveal(int $firmaId, int $casoId, int $id, string $field, Request $request): array
    {
        if (!$this->permissions->allows('partes.revelar', $this->auth->user())) {
            throw new HttpException(403, 'No tiene permiso para revelar datos sensibles de la parte.');
        }
        $data = ['campo' => $field];
        if (!$this->validator->validateReveal($data)) {
            throw new HttpException(422, 'El dato solicitado no es valido.', $this->validator->errors());
        }
        $part = $this->raw($firmaId, $casoId, $id);
        $value = (string) ($part[$field] ?? '');
        $this->audit->record('PARTE_DATO_REVELADO', 'partes', 'caso_parte', $id, [
            'caso_id' => $casoId,
            'campo' => $field,
            'valor_hash' => $value === '' ? null : hash('sha256', $value),
        ], $request, $firmaId, 'warning');

        return ['campo' => $field, 'etiqueta' => $this->sensitiveFields[$field], 'valor' => $value];
    }

    /** @return array<string, mixed> */
    private function raw(int $firmaId, int $casoId, int $id): array
    {
        $this->ensureCase($firmaId, $casoId);

        return $this->repository->findForCase($firmaId, $casoId, $id) ?? throw new HttpException(404, 'La parte no existe en el caso.');
    }

    /** @return array<string, mixed> */
    private function ensureCase(int $firmaId, int $casoId): array
    {
        return $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(404, 'El caso no existe en la firma.');
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, int $casoId, array $data, ?array $before = null): array
    {
        $document = trim((string) $this->sensitiveValue('numero_documento', $data, $before));
        $documentNormalized = preg_replace('/[^A-Za-z0-9]+/', '', strtoupper($document)) ?? '';
        $name = trim((string) ($data['nombre'] ?? ($before['nombre'] ?? '')));

        return [
            'firma_id' => $firmaId,
            'caso_id' => $casoId,
            'tipo_parte' => (string) ($data['tipo_parte'] ?? ($before['tipo_parte'] ?? 'otro')),
            'nombre' => mb_substr($name, 0, 180),
            'nombre_normalizado' => $this->normalizeText($name, 180),
            'tipo_documento' => $this->catalogs->normalizeOptional($firmaId, 'tipo_documento', $data['tipo_documento'] ?? ($before['tipo_documento'] ?? null), 'Tipo de documento'),
            'numero_documento' => $this->nullableString($document, 80),
            'documento_hash' => $documentNormalized === '' ? null : hash('sha256', mb_substr($documentNormalized, 0, 80)),
            'email' => $this->nullableString($this->sensitiveValue('email', $data, $before), 254, true),
            'telefono' => $this->nullableString($this->sensitiveValue('telefono', $data, $before), 60),
            'direccion' => $this->nullableString($this->sensitiveValue('direccion', $data, $before), 255),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'activo')),
            'observaciones' => $this->nullableString($data['observaciones'] ?? ($before['observaciones'] ?? null), 1000),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'caso_id' => $data['caso_id'],
            'tipo_parte' => $data['tipo_parte'],
            'nombre' => $data['nombre'],
            'nombre_normalizado' => $data['nombre_normalizado'],
            'tipo_documento' => $data['tipo_documento'],
            'numero_documento' => $data['numero_documento'],
            'documento_hash' => $data['documento_hash'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'direccion' => $data['direccion'],
            'estado' => $data['estado'],
            'observaciones' => $data['observaciones'],
        ];
    }

    /** @param array<string, mixed> $part @return array<string, mixed> */
    private function masked(array $part): array
    {
        $document = (string) ($part['numero_documento'] ?? '');
        $maskedDocument = $document === '' ? '' : $this->maskEdge($document, 2, 2);
        $maskedEmail = $this->maskEmail((string) ($part['email'] ?? ''));
        $maskedPhone = $this->maskEdge((string) ($part['telefono'] ?? ''), 0, 4);
        $maskedAddress = trim((string) ($part['direccion'] ?? '')) === '' ? '' : 'Registrada';
        unset($part['documento_hash']);

        return array_merge($part, [
            'numero_documento' => $maskedDocument,
            'email' => $maskedEmail,
            'telefono' => $maskedPhone,
            'direccion' => $maskedAddress,
            'numero_documento_masked' => $maskedDocument,
            'email_masked' => $maskedEmail,
            'telefono_masked' => $maskedPhone,
            'direccion_masked' => $maskedAddress,
        ]);
    }

    private function normalizeText(string $value, int $max): string
    {
        $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';

        return mb_substr($value, 0, $max);
    }

    private function nullableString(mixed $value, int $max, bool $lower = false): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($lower ? strtolower($value) : $value, 0, $max);
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before */
    private function sensitiveValue(string $field, array $data, ?array $before): mixed
    {
        $value = $data[$field] ?? null;
        if ($before !== null && trim((string) $value) === '') {
            return $before[$field] ?? null;
        }

        return $value;
    }

    private function maskEdge(string $value, int $left, int $right): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $length = mb_strlen($value);
        if ($length <= ($left + $right + 2)) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, $left) . str_repeat('*', max(4, $length - $left - $right)) . ($right > 0 ? mb_substr($value, -$right) : '');
    }

    private function maskEmail(string $email): string
    {
        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', $email, 2);

        return $this->maskEdge($name, 1, 0) . '@' . $domain;
    }
}
