<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\SuperadminRolRepository;
use App\Repositories\SuperadminUsuarioRepository;

final class SuperadminUsuarioService
{
    public function __construct(
        private readonly SuperadminUsuarioRepository $repository,
        private readonly SuperadminRolRepository $rolRepo,
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
        return $this->repository->find($id)
            ?? throw new HttpException(404, 'El usuario superadmin no existe.');
    }

    /** @return array<string, mixed> */
    public function stats(int $currentUserId): array
    {
        return [
            'activos'      => $this->repository->countActive(),
            'ultimo_login' => $this->repository->lastLogin($currentUserId),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, Request $request): int
    {
        $this->validateCreate($data);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($this->repository->emailScopeExists($email)) {
            throw new HttpException(409, 'El correo ya está registrado.', ['email' => ['El correo ya existe.']]);
        }

        return $this->database->transaction(function () use ($data, $email, $request): int {
            $id = $this->repository->create([
                'nombre'            => trim((string) ($data['nombre'] ?? '')),
                'email'             => $email,
                'email_normalizado' => $email,
                'email_scope'       => $email,
                'password_hash'     => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                'cargo'             => trim((string) ($data['cargo'] ?? '')),
            ]);
            $this->audit->record(
                'SUPERADMIN_USUARIO_CREADO',
                'superadmin_usuarios',
                'usuario',
                $id,
                ['email_hash' => hash('sha256', $email)],
                $request,
                null
            );

            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $currentUserId, int $id, array $data, Request $request): void
    {
        if ($id === $currentUserId) {
            throw new HttpException(403, 'No puedes editarte a ti mismo desde este panel.');
        }
        $this->find($id);

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Email inválido.', ['email' => ['Formato de email inválido.']]);
        }
        if ($this->repository->emailScopeExists($email, $id)) {
            throw new HttpException(409, 'El correo ya está en uso.', ['email' => ['El correo ya existe.']]);
        }

        $this->repository->updateProfile($id, [
            'nombre'            => trim((string) ($data['nombre'] ?? '')),
            'email'             => $email,
            'email_normalizado' => $email,
            'email_scope'       => $email,
            'cargo'             => trim((string) ($data['cargo'] ?? '')),
        ]);

        // Sincronizar roles si se envían
        if (array_key_exists('roles', $data)) {
            $roleIds = array_map('intval', (array) ($data['roles'] ?? []));
            $this->rolRepo->syncUserRoles($id, $roleIds);
        }

        $this->audit->record('SUPERADMIN_USUARIO_EDITADO', 'superadmin_usuarios', 'usuario', $id, [], $request, null);
    }

    public function deactivate(int $currentUserId, int $id, Request $request): void
    {
        if ($id === $currentUserId) {
            throw new HttpException(403, 'No puedes desactivarte a ti mismo.');
        }
        $this->find($id);
        $this->repository->deactivate($id);
        $this->audit->record('SUPERADMIN_USUARIO_DESACTIVADO', 'superadmin_usuarios', 'usuario', $id, [], $request, null);
    }

    public function reactivate(int $id, Request $request): void
    {
        $this->find($id);
        $this->repository->reactivate($id);
        $this->audit->record('SUPERADMIN_USUARIO_REACTIVADO', 'superadmin_usuarios', 'usuario', $id, [], $request, null);
    }

    public function resetPassword(int $id, Request $request): string
    {
        $this->find($id);
        $tempPass = $this->generatePassword();
        $this->repository->setPassword($id, password_hash($tempPass, PASSWORD_DEFAULT));
        $this->audit->record('SUPERADMIN_USUARIO_PASSWORD_RESET', 'superadmin_usuarios', 'usuario', $id, [], $request, null);

        return $tempPass;
    }

    /** @param array<string, mixed> $data */
    private function validateCreate(array $data): void
    {
        $errors  = [];
        $nombre  = trim((string) ($data['nombre'] ?? ''));
        $email   = trim((string) ($data['email'] ?? ''));
        $pass    = (string) ($data['password'] ?? '');
        $confirm = (string) ($data['password_confirm'] ?? '');

        if (mb_strlen($nombre) < 3) {
            $errors['nombre'][] = 'El nombre debe tener al menos 3 caracteres.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Formato de email inválido.';
        }
        if (mb_strlen($pass) < 8) {
            $errors['password'][] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($pass !== $confirm) {
            $errors['password_confirm'][] = 'Las contraseñas no coinciden.';
        }

        if ($errors !== []) {
            throw new HttpException(422, 'Revise los datos del usuario.', $errors);
        }
    }

    private function generatePassword(): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
        $pass  = '';
        for ($i = 0; $i < 12; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $pass;
    }
}
