<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\RolRepository;
use App\Repositories\UserSessionRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\UsuarioValidator;

final class UsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $repository,
        private readonly RolRepository $roles,
        private readonly UserSessionRepository $sessions,
        private readonly UsuarioValidator $validator,
        private readonly LimitePlanService $limits,
        private readonly Database $database,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(int $firmaId): array
    {
        return $this->repository->allForFirma($firmaId);
    }

    /** @return array<string, mixed> */
    public function find(int $firmaId, int $id): array
    {
        $user = $this->repository->findForFirma($firmaId, $id) ?? throw new HttpException(404, 'El usuario no existe en la firma.');
        $user['roles'] = $this->repository->roles($id, $firmaId);

        return $user;
    }

    /** @param array<string, mixed> $data @param list<int> $roleIds */
    public function create(int $firmaId, array $data, array $roleIds, Request $request): int
    {
        $this->limits->requireCapacity($firmaId, 'usuarios');
        $normalized = $this->normalize($firmaId, $data);
        if (!$this->validator->validateCreate($normalized)) {
            throw new HttpException(422, 'Revise los datos del usuario.', $this->validator->errors());
        }
        if ($this->repository->emailScopeExists($normalized['email_scope'])) {
            throw new HttpException(409, 'El correo ya está registrado en esta firma.', ['email' => ['El correo ya existe.']]);
        }
        if ($normalized['tipo'] === 'cliente_externo' && $roleIds !== []) {
            throw new HttpException(422, 'Los usuarios externos no pueden recibir roles internos.');
        }

        return $this->database->transaction(function () use ($firmaId, $normalized, $roleIds, $request): int {
            $record = $normalized;
            $record['password_hash'] = password_hash($normalized['password'], PASSWORD_DEFAULT);
            $record['must_change_password'] = 1;
            unset($record['password']);
            $id = $this->repository->create($record);
            $this->roles->syncUserRoles($firmaId, $id, array_map('intval', $roleIds));
            $this->audit->record('USUARIO_CREADO', 'usuarios', 'usuario', $id, ['email_hash' => hash('sha256', $normalized['email']), 'tipo' => $normalized['tipo']], $request, $firmaId);

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function createFirstAdmin(int $firmaId, array $data, Request $request): int
    {
        $adminRole = $this->roles->findByCode($firmaId, 'administrador') ?? throw new HttpException(409, 'La firma no tiene un rol administrador aprovisionado.');

        return $this->create($firmaId, $data + ['tipo' => 'interno'], [(int) $adminRole['id']], $request);
    }

    /** @param array<string, mixed> $data */
    public function update(int $firmaId, int $id, array $data, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->normalize($firmaId, $data);
        if (!$this->validator->validateUpdate($normalized)) {
            throw new HttpException(422, 'Revise los datos del usuario.', $this->validator->errors());
        }
        if ($this->repository->emailScopeExists($normalized['email_scope'], $id)) {
            throw new HttpException(409, 'El correo ya está registrado en esta firma.');
        }
        $this->repository->update($firmaId, $id, [
            'nombre' => $normalized['nombre'],
            'email' => $normalized['email'],
            'email_normalizado' => $normalized['email_normalizado'],
            'email_scope' => $normalized['email_scope'],
            'tipo' => $normalized['tipo'],
        ]);
        $this->audit->record('USUARIO_MODIFICADO', 'usuarios', 'usuario', $id, [
            'anterior' => ['nombre' => $before['nombre'], 'email_hash' => hash('sha256', (string) $before['email']), 'tipo' => $before['tipo']],
            'nuevo' => ['nombre' => $normalized['nombre'], 'email_hash' => hash('sha256', $normalized['email']), 'tipo' => $normalized['tipo']],
        ], $request, $firmaId);
    }

    public function deactivate(int $firmaId, int $id, Request $request): void
    {
        $user = $this->find($firmaId, $id);
        if ($this->repository->hasRole($id, $firmaId, 'administrador') && $this->repository->countActiveAdmins($firmaId) <= 1) {
            throw new HttpException(409, 'No se puede dejar la firma sin un administrador activo.');
        }
        $this->database->transaction(function () use ($firmaId, $id, $request): void {
            $this->repository->setStatus($firmaId, $id, 'inactivo');
            $this->sessions->revokeAllForUser($id, 'usuario_desactivado');
            $this->audit->record('USUARIO_DESACTIVADO', 'usuarios', 'usuario', $id, [], $request, $firmaId, 'warning');
        });
    }

    public function reactivate(int $firmaId, int $id, Request $request): void
    {
        $this->find($firmaId, $id);
        $this->repository->setStatus($firmaId, $id, 'activo');
        $this->audit->record('USUARIO_REACTIVADO', 'usuarios', 'usuario', $id, [], $request, $firmaId);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(int $firmaId, array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        return [
            'firma_id' => $firmaId,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'email' => $email,
            'email_normalizado' => $email,
            'email_scope' => 'firma:' . $firmaId . ':' . $email,
            'tipo' => (string) ($data['tipo'] ?? 'interno'),
            'password' => (string) ($data['password'] ?? ''),
        ];
    }
}
