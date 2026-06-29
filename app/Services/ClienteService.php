<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Permission;
use App\Core\Request;
use App\Repositories\ClienteRepository;
use App\Validators\ClienteValidator;

final class ClienteService
{
    /** @var array<string, string> */
    private array $sensitiveFields = [
        'numero_documento' => 'Documento',
        'email' => 'Correo',
        'telefono' => 'Telefono',
        'direccion' => 'Direccion',
    ];

    public function __construct(
        private readonly ClienteRepository $repository,
        private readonly ClienteValidator $validator,
        private readonly LimitePlanService $limits,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly Permission $permissions,
        private readonly CatalogoLookupService $catalogs
    ) {
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public function list(int $firmaId, array $filters, int $page = 1, int $perPage = 25): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $result = $this->repository->paginate($firmaId, $normalizedFilters, max(1, $page), min(100, max(1, $perPage)));
        $result['items'] = array_map(fn (array $client): array => $this->masked($client), $result['items']);
        $result['page'] = max(1, $page);
        $result['per_page'] = min(100, max(1, $perPage));

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        $client = $this->raw($firmaId, $id);
        $masked = $this->masked($client);
        $masked['observaciones'] = $client['observaciones'];
        $masked['autorizaciones'] = $this->repository->authorizations($firmaId, $id);
        $masked['ficha360'] = $this->filterFicha360($this->repository->ficha360($firmaId, $id));

        return $masked;
    }

    /** @param array<string, mixed> $data */
    public function create(int $firmaId, array $data, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'clientes', $request);
        $normalized = $this->normalize($firmaId, $data);
        if (!$this->validator->validateCreate($normalized)) {
            throw new HttpException(422, 'Revise los datos del cliente.', $this->validator->errors());
        }
        $this->ensureDocumentIsAvailable($firmaId, $normalized['documento_hash']);

        return $this->database->transaction(function () use ($firmaId, $normalized, $request): int {
            $id = $this->repository->create($this->recordData($normalized));
            if ((int) $normalized['tratamiento_datos_autorizado'] === 1) {
                $this->registerAuthorization($firmaId, $id, $normalized, $request);
            }
            $this->audit->record('CLIENTE_CREADO', 'clientes', 'cliente', $id, [
                'tipo_persona' => $normalized['tipo_persona'],
                'estado' => $normalized['estado'],
                'documento_hash' => $normalized['documento_hash'],
            ], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->raw($firmaId, $id);
        $normalized = $this->normalize($firmaId, $data, $before);
        if (!$this->validator->validateUpdate($normalized)) {
            throw new HttpException(422, 'Revise los datos del cliente.', $this->validator->errors());
        }
        $this->ensureDocumentIsAvailable($firmaId, $normalized['documento_hash'], $id);

        $this->database->transaction(function () use ($firmaId, $id, $before, $normalized, $request): void {
            $this->repository->update($firmaId, $id, $this->recordData($normalized));
            if ((int) $normalized['tratamiento_datos_autorizado'] === 1 && (int) ($before['tratamiento_datos_autorizado'] ?? 0) !== 1) {
                $this->registerAuthorization($firmaId, $id, $normalized, $request);
            }
            $this->audit->record('CLIENTE_MODIFICADO', 'clientes', 'cliente', $id, [
                'anterior' => [
                    'nombre' => $before['nombre_razon_social'],
                    'tipo_persona' => $before['tipo_persona'],
                    'estado' => $before['estado'],
                    'documento_hash' => $before['documento_hash'],
                ],
                'nuevo' => [
                    'nombre' => $normalized['nombre_razon_social'],
                    'tipo_persona' => $normalized['tipo_persona'],
                    'estado' => $normalized['estado'],
                    'documento_hash' => $normalized['documento_hash'],
                ],
            ], $request, $firmaId);
        });
    }

    public function delete(int $firmaId, int $id, Request $request): void
    {
        $client = $this->raw($firmaId, $id);
        $this->repository->softDelete($firmaId, $id);
        $this->audit->record('CLIENTE_ELIMINADO', 'clientes', 'cliente', $id, [
            'nombre' => $client['nombre_razon_social'],
            'documento_hash' => $client['documento_hash'],
        ], $request, $firmaId, 'warning');
    }

    /** @return array{campo: string, etiqueta: string, valor: string} */
    public function reveal(int $firmaId, int $id, string $field, Request $request): array
    {
        if (!$this->permissions->allows('clientes.revelar', $this->auth->user())) {
            throw new HttpException(403, 'No tiene permiso para revelar datos sensibles del cliente.');
        }
        $normalized = ['campo' => $field];
        if (!$this->validator->validateReveal($normalized)) {
            throw new HttpException(422, 'El dato solicitado no es valido.', $this->validator->errors());
        }
        $client = $this->raw($firmaId, $id);
        $value = (string) ($client[$field] ?? '');
        $this->audit->record('CLIENTE_DATO_REVELADO', 'clientes', 'cliente', $id, [
            'campo' => $field,
            'valor_hash' => $value === '' ? null : hash('sha256', $value),
        ], $request, $firmaId, 'warning');

        return ['campo' => $field, 'etiqueta' => $this->sensitiveFields[$field], 'valor' => $value];
    }

    /** @return array<string, mixed> */
    private function raw(int $firmaId, int $id): array
    {
        return $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El cliente no existe en la firma.');
    }

    /** @param array<string, mixed> $ficha @return array<string, mixed> */
    private function filterFicha360(array $ficha): array
    {
        $user = $this->auth->user();
        $allowed = [
            'casos' => $this->permissions->allows('casos.ver', $user),
            'documentos' => $this->permissions->allows('documentos.ver', $user),
            'finanzas' => $this->permissions->allows('finanzas.ver', $user),
            'portal' => $this->permissions->allows('portal.autorizar', $user),
            'actividad' => $this->permissions->allows('auditoria.ver', $user),
        ];
        if (!$allowed['casos']) {
            $ficha['casos'] = [];
            $ficha['resumen']['casos'] = null;
        }
        if (!$allowed['documentos']) {
            $ficha['documentos'] = [];
            $ficha['resumen']['documentos'] = null;
        }
        if (!$allowed['finanzas']) {
            $ficha['finanzas'] = [];
            $ficha['resumen']['honorarios'] = null;
            $ficha['resumen']['pagos'] = null;
            $ficha['resumen']['gastos'] = null;
        }
        if (!$allowed['portal']) {
            $ficha['portal'] = [];
            $ficha['resumen']['portal_accesos'] = null;
        }
        if (!$allowed['actividad']) {
            $ficha['actividad'] = [];
        }
        $ficha['permisos'] = $allowed;

        return $ficha;
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $document = trim((string) ($data['numero_documento'] ?? ''));
        if ($document === '' && $before !== null) {
            $document = (string) ($before['numero_documento'] ?? '');
        }
        $documentNormalized = $this->normalizeDocument($document);
        $authorized = $this->truthy($data['tratamiento_datos_autorizado'] ?? ($before['tratamiento_datos_autorizado'] ?? 0));

        return [
            'firma_id' => $firmaId,
            'tipo_persona' => (string) ($data['tipo_persona'] ?? ($before['tipo_persona'] ?? 'natural')),
            'nombre_razon_social' => trim((string) ($data['nombre_razon_social'] ?? ($before['nombre_razon_social'] ?? ''))),
            'nombre_normalizado' => $this->normalizeName((string) ($data['nombre_razon_social'] ?? ($before['nombre_razon_social'] ?? ''))),
            'tipo_documento' => $this->catalogs->normalizeOptional($firmaId, 'tipo_documento', $data['tipo_documento'] ?? ($before['tipo_documento'] ?? null), 'Tipo de documento'),
            'numero_documento' => $this->nullableString($document, 80),
            'documento_normalizado' => $documentNormalized === '' ? null : $documentNormalized,
            'documento_hash' => $documentNormalized === '' ? null : hash('sha256', $documentNormalized),
            'email' => $this->nullableString($this->sensitiveValue('email', $data, $before), 254, true),
            'telefono' => $this->nullableString($this->sensitiveValue('telefono', $data, $before), 60),
            'direccion' => $this->nullableString($this->sensitiveValue('direccion', $data, $before), 255),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'activo')),
            'origen' => $this->catalogs->normalizeOptional($firmaId, 'origen_fuente', $data['origen'] ?? ($before['origen'] ?? null), 'Origen'),
            'observaciones' => $this->nullableString($data['observaciones'] ?? ($before['observaciones'] ?? null), 1000),
            'tratamiento_datos_autorizado' => $authorized ? 1 : 0,
            'autorizacion_tratamiento_at' => $authorized ? ($before['autorizacion_tratamiento_at'] ?? date('Y-m-d H:i:s')) : null,
            'autorizacion_medio' => $this->catalogs->normalizeOptional($firmaId, 'medio', $data['autorizacion_medio'] ?? null, 'Medio de autorizacion'),
            'autorizacion_version' => $this->nullableString($data['autorizacion_version'] ?? null, 80),
            'autorizacion_observacion' => $this->nullableString($data['autorizacion_observacion'] ?? null, 500),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function recordData(array $data): array
    {
        return [
            'firma_id' => $data['firma_id'],
            'tipo_persona' => $data['tipo_persona'],
            'nombre_razon_social' => $data['nombre_razon_social'],
            'nombre_normalizado' => $data['nombre_normalizado'],
            'tipo_documento' => $data['tipo_documento'],
            'numero_documento' => $data['numero_documento'],
            'documento_normalizado' => $data['documento_normalizado'],
            'documento_hash' => $data['documento_hash'],
            'email' => $data['email'],
            'telefono' => $data['telefono'],
            'direccion' => $data['direccion'],
            'estado' => $data['estado'],
            'origen' => $data['origen'],
            'observaciones' => $data['observaciones'],
            'tratamiento_datos_autorizado' => $data['tratamiento_datos_autorizado'],
            'autorizacion_tratamiento_at' => $data['autorizacion_tratamiento_at'],
        ];
    }

    /** @param array<string, mixed> $client @return array<string, mixed> */
    private function masked(array $client): array
    {
        $document = (string) ($client['numero_documento'] ?? '');
        $maskedDocument = $document === '' ? '' : $this->maskEdge($document, 2, 2);
        $maskedEmail = $this->maskEmail((string) ($client['email'] ?? ''));
        $maskedPhone = $this->maskEdge((string) ($client['telefono'] ?? ''), 0, 4);
        $maskedAddress = trim((string) ($client['direccion'] ?? '')) === '' ? '' : 'Registrada';
        unset($client['documento_normalizado'], $client['documento_hash']);

        return array_merge($client, [
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

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $query = $this->normalizeName((string) ($filters['q'] ?? ''));
        $document = $this->normalizeDocument((string) ($filters['q'] ?? ''));

        return [
            'q' => $query,
            'documento_query' => $document,
            'documento_hash' => $document === '' ? '' : hash('sha256', $document),
            'estado' => in_array(($filters['estado'] ?? ''), ['activo', 'inactivo'], true) ? $filters['estado'] : '',
            'tipo_persona' => in_array(($filters['tipo_persona'] ?? ''), ['natural', 'juridica'], true) ? $filters['tipo_persona'] : '',
        ];
    }

    private function registerAuthorization(int $firmaId, int $clientId, array $data, Request $request): void
    {
        $authorizationId = $this->repository->createAuthorization([
            'firma_id' => $firmaId,
            'cliente_id' => $clientId,
            'tipo' => 'tratamiento_datos',
            'estado' => 'otorgada',
            'medio' => $data['autorizacion_medio'] ?: 'registro_interno',
            'version_texto' => $data['autorizacion_version'],
            'evidencia_hash' => hash('sha256', $firmaId . '|' . $clientId . '|' . microtime(true) . '|' . (string) $this->auth->id()),
            'observacion' => $data['autorizacion_observacion'],
            'registrado_por_usuario_id' => $this->auth->id(),
        ]);
        $this->audit->record('CLIENTE_AUTORIZACION_REGISTRADA', 'clientes', 'cliente_autorizacion', $authorizationId, [
            'cliente_id' => $clientId,
            'tipo' => 'tratamiento_datos',
            'medio' => $data['autorizacion_medio'] ?: 'registro_interno',
        ], $request, $firmaId);
    }

    private function ensureDocumentIsAvailable(int $firmaId, ?string $documentHash, ?int $excludeId = null): void
    {
        if ($documentHash !== null && $this->repository->documentHashExists($firmaId, $documentHash, $excludeId)) {
            throw new HttpException(409, 'Ya existe un cliente activo con ese documento en la firma.');
        }
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

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return mb_substr($value, 0, 180);
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
        if ($lower) {
            $value = strtolower($value);
        }

        return mb_substr($value, 0, $max);
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'si', 'yes'], true);
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
