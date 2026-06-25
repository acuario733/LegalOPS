<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserSessionRepository;
use App\Repositories\UsuarioRepository;
use App\Validators\LoginValidator;

final class AuthService
{
    public function __construct(
        private readonly UsuarioRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly PasswordResetRepository $resets,
        private readonly UserSessionRepository $sessions,
        private readonly LoginValidator $validator,
        private readonly Auth $auth,
        private readonly SessionService $sessionService,
        private readonly LocalMailService $mail,
        private readonly Database $database,
        private readonly AuditoriaService $audit
    ) {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function login(array $data, Request $request): array
    {
        $credentials = [
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'password' => (string) ($data['password'] ?? ''),
            'firma' => strtolower(trim((string) ($data['firma'] ?? ''))),
        ];
        if (!$this->validator->validateData($credentials)) {
            throw new HttpException(422, 'No fue posible iniciar sesión con los datos proporcionados.', $this->validator->errors());
        }

        $emailHash = hash('sha256', $credentials['email']);
        if ($this->attempts->countRecentFailures($emailHash, $request->ip()) >= 5) {
            throw new HttpException(429, 'Demasiados intentos. Espere antes de volver a intentarlo.');
        }
        $candidates = $this->users->findLoginCandidates($credentials['email'], $credentials['firma'] ?: null);
        $user = count($candidates) === 1 ? $candidates[0] : null;
        $valid = is_array($user)
            && is_string($user['password_hash'])
            && password_verify($credentials['password'], $user['password_hash'])
            && $user['estado'] === 'activo'
            && ($user['firma_id'] === null || $user['firma_estado'] === 'activa');

        $firmaId = is_array($user) && $user['firma_id'] !== null ? (int) $user['firma_id'] : null;
        $this->attempts->record($firmaId, $emailHash, $request->ip(), $valid);
        if (!$valid) {
            $this->audit->record('LOGIN_FALLIDO', 'auth', 'usuario', is_array($user) ? (int) $user['id'] : null, ['email_hash' => $emailHash], $request, $firmaId, 'warning');
            throw new HttpException(422, 'No fue posible iniciar sesión con los datos proporcionados.');
        }

        $sessionUser = [
            'id' => (int) $user['id'],
            'firma_id' => $firmaId,
            'name' => (string) $user['nombre'],
            'email' => (string) $user['email'],
            'tipo' => (string) $user['tipo'],
            'firma_estado' => $user['firma_estado'] ?? null,
            'permissions' => $user['tipo'] === 'superadmin' ? ['*'] : $this->users->permissionCodes((int) $user['id'], (int) $firmaId),
            'roles' => $firmaId === null ? [] : $this->users->roles((int) $user['id'], $firmaId),
        ];

        $this->auth->login($sessionUser);
        $this->sessionService->registerCurrent($sessionUser, $request);
        $this->users->updateLastLogin((int) $user['id']);
        $this->audit->record('LOGIN_EXITOSO', 'auth', 'usuario', (int) $user['id'], [], $request, $firmaId);

        return $sessionUser;
    }

    public function logout(Request $request): void
    {
        $userId = $this->auth->id();
        $firmaId = $this->auth->firmaId();
        if ($userId !== null) {
            $this->sessionService->revokeCurrent('logout');
            $this->audit->record('LOGOUT', 'auth', 'usuario', (int) $userId, [], $request, $firmaId === null ? null : (int) $firmaId);
        }
        $this->auth->logout();
    }

    public function requestPasswordReset(string $email, ?string $firmaSlug, Request $request): void
    {
        $email = strtolower(trim($email));
        $candidates = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->users->findLoginCandidates($email, $firmaSlug) : [];
        if (count($candidates) !== 1) {
            return;
        }
        $user = $candidates[0];
        $token = bin2hex(random_bytes(32));
        $this->resets->create((int) $user['id'], $user['firma_id'] === null ? null : (int) $user['firma_id'], hash('sha256', $token), $request->ip(), $request->userAgent());
        $this->mail->sendPasswordReset((string) $user['email'], $token);
        $this->audit->record('PASSWORD_RESET_SOLICITADO', 'auth', 'usuario', (int) $user['id'], [], $request, $user['firma_id'] === null ? null : (int) $user['firma_id']);
    }

    public function resetPassword(string $token, string $password, Request $request): void
    {
        if (strlen($password) < 12) {
            throw new HttpException(422, 'La nueva contraseña debe tener al menos 12 caracteres.');
        }
        $reset = $this->resets->findValid(hash('sha256', $token)) ?? throw new HttpException(422, 'El enlace de recuperación no es válido o expiró.');
        $this->database->transaction(function () use ($reset, $password, $request): void {
            $this->users->updatePassword((int) $reset['usuario_id'], password_hash($password, PASSWORD_DEFAULT));
            $this->resets->markUsed((int) $reset['id']);
            $this->sessions->revokeAllForUser((int) $reset['usuario_id'], 'password_reset');
            $this->audit->record('PASSWORD_CAMBIADA', 'auth', 'usuario', (int) $reset['usuario_id'], [], $request, $reset['firma_id'] === null ? null : (int) $reset['firma_id']);
        });
    }
}

