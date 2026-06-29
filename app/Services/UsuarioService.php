<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
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
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly SensitiveDataService $sensitive
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(int $firmaId): array
    {
        return array_map(function (array $user) use ($firmaId): array {
            $user['roles_detalle'] = $this->repository->roles((int) $user['id'], $firmaId);
            $user['numero_tarjeta_profesional_enmascarado'] = $this->sensitive->maskProfessionalCard(
                (string) ($user['numero_tarjeta_profesional_normalizado'] ?? $user['numero_tarjeta_profesional'] ?? '')
            );

            return $user;
        }, $this->repository->allForFirma($firmaId));
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
        $this->limits->requireCapacity($firmaId, 'usuarios', $request);
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

    /** @param array<string, mixed> $data @param list<int> $roleIds */
    public function update(int $firmaId, int $id, array $data, array $roleIds, Request $request): void
    {
        $before = $this->find($firmaId, $id);
        $normalized = $this->normalize($firmaId, $data, $before);
        if (!$this->validator->validateUpdate($normalized)) {
            throw new HttpException(422, 'Revise los datos del usuario.', $this->validator->errors());
        }
        if ($this->repository->emailScopeExists($normalized['email_scope'], $id)) {
            throw new HttpException(409, 'El correo ya está registrado en esta firma.');
        }
        if ($normalized['tipo'] === 'cliente_externo') {
            $roleIds = [];
        }
        if ((string) $before['estado'] === 'activo'
            && $this->repository->hasRole($id, $firmaId, 'administrador')
            && $this->repository->countActiveAdmins($firmaId) <= 1
            && ($normalized['estado'] === 'inactivo' || !$this->containsAdminRole($firmaId, array_map('intval', $roleIds)))) {
            throw new HttpException(409, 'No se puede dejar la firma sin un administrador activo.');
        }

        $this->database->transaction(function () use ($firmaId, $id, $before, $normalized, $roleIds, $request): void {
            $this->repository->update($firmaId, $id, [
                'nombre' => $normalized['nombre'],
                'email' => $normalized['email'],
                'email_normalizado' => $normalized['email_normalizado'],
                'email_scope' => $normalized['email_scope'],
                'tipo' => $normalized['tipo'],
            ]);
            if ((string) $before['estado'] !== $normalized['estado']) {
                $this->repository->setStatus($firmaId, $id, $normalized['estado']);
                if ($normalized['estado'] === 'inactivo') {
                    $this->sessions->revokeAllForUser($id, 'usuario_desactivado');
                }
            }
            $this->roles->syncUserRoles($firmaId, $id, array_map('intval', $roleIds));
            $this->audit->record('USUARIO_MODIFICADO', 'usuarios', 'usuario', $id, [
                'anterior' => ['nombre' => $before['nombre'], 'email_hash' => hash('sha256', (string) $before['email']), 'tipo' => $before['tipo'], 'estado' => $before['estado']],
                'nuevo' => ['nombre' => $normalized['nombre'], 'email_hash' => hash('sha256', $normalized['email']), 'tipo' => $normalized['tipo'], 'estado' => $normalized['estado'], 'roles' => array_map('intval', $roleIds)],
            ], $request, $firmaId);
        });
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

    /** @param array<string, mixed> $data */
    public function verifyProfessionalCard(int $firmaId, int $id, array $data, Request $request): void
    {
        $actorId = $this->auth->id();
        if (!is_int($actorId) && !(is_string($actorId) && ctype_digit($actorId))) {
            throw new HttpException(401, 'Debe iniciar sesión para verificar tarjetas profesionales.');
        }
        $actorId = (int) $actorId;
        if ($actorId === $id) {
            throw new HttpException(403, 'El titular no puede verificar su propia tarjeta profesional.');
        }

        $user = $this->find($firmaId, $id);
        if ((int) ($user['tiene_tarjeta_profesional'] ?? 0) !== 1 || trim((string) ($user['numero_tarjeta_profesional_normalizado'] ?? '')) === '') {
            throw new HttpException(422, 'El usuario no tiene tarjeta profesional registrada para verificar.');
        }

        $status = (string) ($data['estado'] ?? '');
        if (!in_array($status, ['verificada', 'rechazada'], true)) {
            throw new HttpException(422, 'Seleccione una decisión válida para la tarjeta profesional.', [
                'estado' => ['La decisión debe ser verificada o rechazada.'],
            ]);
        }

        $observation = preg_replace('/\s+/', ' ', trim((string) ($data['observacion'] ?? ''))) ?? '';
        $observation = $observation === '' ? null : mb_substr($observation, 0, 500);

        $this->database->transaction(function () use ($firmaId, $id, $actorId, $status, $observation, $request): void {
            $this->repository->verifyProfessionalCard($firmaId, $id, $actorId, $status, $observation);
            $this->audit->record(
                $status === 'verificada' ? 'USUARIO_TARJETA_PROFESIONAL_VERIFICADA' : 'USUARIO_TARJETA_PROFESIONAL_RECHAZADA',
                'usuarios',
                'usuario',
                $id,
                [
                    'estado' => $status,
                    'observacion_presente' => $observation !== null,
                    'origen' => 'usuarios',
                ],
                $request,
                $firmaId
            );
        });
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $before @return array<string, mixed> */
    private function normalize(int $firmaId, array $data, ?array $before = null): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? ($before['email'] ?? ''))));

        return [
            'firma_id' => $firmaId,
            'nombre' => trim((string) ($data['nombre'] ?? ($before['nombre'] ?? ''))),
            'email' => $email,
            'email_normalizado' => $email,
            'email_scope' => 'firma:' . $firmaId . ':' . $email,
            'tipo' => (string) ($data['tipo'] ?? ($before['tipo'] ?? 'interno')),
            'estado' => (string) ($data['estado'] ?? ($before['estado'] ?? 'activo')),
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    /** @param list<int> $roleIds */
    private function containsAdminRole(int $firmaId, array $roleIds): bool
    {
        $adminRole = $this->roles->findByCode($firmaId, 'administrador');
        if ($adminRole === null) {
            return false;
        }

        return in_array((int) $adminRole['id'], array_map('intval', $roleIds), true);
    }
}
