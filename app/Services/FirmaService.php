<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\FirmaRepository;
use App\Repositories\RolRepository;
use App\Repositories\UserSessionRepository;
use App\Validators\FirmaValidator;

final class FirmaService
{
    public function __construct(
        private readonly FirmaRepository $repository,
        private readonly RolRepository $roles,
        private readonly UserSessionRepository $sessions,
        private readonly FirmaValidator $validator,
        private readonly Database $database,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->repository->all();
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        return $this->repository->find($id) ?? throw new HttpException(404, 'La firma solicitada no existe.');
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $request): int
    {
        $data = $this->normalize($data);
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos de la firma.', $this->validator->errors());
        }
        if ($this->repository->findBySlug($data['slug']) !== null) {
            throw new HttpException(409, 'El identificador URL de la firma ya está en uso.', ['slug' => ['El slug ya existe.']]);
        }

        return $this->database->transaction(function () use ($data, $request): int {
            $id = $this->repository->create($data + ['uuid' => $this->uuid()]);
            $roleId = $this->roles->create($id, [
                'codigo' => 'administrador',
                'nombre' => 'Administrador de firma',
                'descripcion' => 'Rol base protegido con administración completa de la firma.',
                'estado' => 'activo',
                'is_protected' => 1,
            ]);
            $tenantPermissions = array_map('strval', (array) Config::get('permissions.firma', []));
            $permissionIds = array_map(
                static fn (array $permission): int => (int) $permission['id'],
                array_filter($this->roles->permissions(), static fn (array $permission): bool => in_array((string) $permission['codigo'], $tenantPermissions, true))
            );
            $this->roles->syncPermissions($id, $roleId, $permissionIds);
            $this->audit->record('FIRMA_CREADA', 'firmas', 'firma', $id, ['nombre' => $data['nombre']], $request, $id);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, Request $request): void
    {
        $before = $this->find($id);
        $data = $this->normalize($data);
        if (!$this->validator->validateData($data)) {
            throw new HttpException(422, 'Revise los datos de la firma.', $this->validator->errors());
        }
        $slugOwner = $this->repository->findBySlug($data['slug']);
        if ($slugOwner !== null && (int) $slugOwner['id'] !== $id) {
            throw new HttpException(409, 'El identificador URL de la firma ya está en uso.');
        }

        $this->database->transaction(function () use ($id, $data, $before, $request): void {
            $this->repository->update($id, $data);
            $this->audit->record('FIRMA_MODIFICADA', 'firmas', 'firma', $id, [
                'anterior' => ['nombre' => $before['nombre'], 'slug' => $before['slug'], 'timezone' => $before['timezone']],
                'nuevo' => $data,
            ], $request, $id);
        });
    }

    public function suspend(int $id, string $reason, Request $request): void
    {
        $this->find($id);
        if (mb_strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'Debe indicar un motivo de suspensión válido.');
        }
        $this->database->transaction(function () use ($id, $reason, $request): void {
            $this->repository->setStatus($id, 'suspendida', mb_substr(trim($reason), 0, 255));
            $this->sessions->revokeAllForFirma($id, 'firma_suspendida');
            $this->audit->record('FIRMA_SUSPENDIDA', 'firmas', 'firma', $id, ['motivo' => $reason], $request, $id, 'warning');
        });
    }

    public function reactivate(int $id, Request $request): void
    {
        $this->find($id);
        $this->database->transaction(function () use ($id, $request): void {
            $this->repository->setStatus($id, 'activa', null);
            $this->audit->record('FIRMA_REACTIVADA', 'firmas', 'firma', $id, [], $request, $id);
        });
    }

    /** @return array<string, int> */
    public function usage(int $id): array
    {
        $this->find($id);

        return $this->repository->usage($id);
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    private function normalize(array $data): array
    {
        $slug = strtolower(trim((string) ($data['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return [
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'slug' => trim($slug, '-'),
            'timezone' => trim((string) ($data['timezone'] ?? 'UTC')),
        ];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
