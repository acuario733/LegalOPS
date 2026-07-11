<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\UsuarioRepository;
use App\Repositories\UserSessionRepository;

final class SessionService
{
    public function __construct(
        private readonly UserSessionRepository $repository,
        private readonly UsuarioRepository $users,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly AuditoriaService $audit,
        private readonly CommercialStatusService $commercialStatus
    ) {
    }

    /** @param array<string, mixed> $user */
    public function registerCurrent(array $user, Request $request): void
    {
        $this->repository->register(
            (int) $user['id'],
            isset($user['firma_id']) ? (int) $user['firma_id'] : null,
            $this->session->id(),
            $request->userAgent() . '|' . $request->ip(),
            $request->ip(),
            $request->userAgent(),
            (int) Config::get('security.session.lifetime_minutes', 120)
        );
    }

    public function currentIsActive(): bool
    {
        $id = $this->auth->id();
        if ($id === null) {
            return false;
        }

        if (!$this->repository->isActive((int) $id, $this->session->id())) {
            return false;
        }

        $user = $this->users->findById((int) $id);
        if ($user === null || ($user['estado'] ?? null) !== 'activo') {
            $this->repository->revokeCurrent((int) $id, $this->session->id(), 'usuario_no_activo');

            return false;
        }

        if ($user['firma_id'] !== null && !$this->commercialStatus->allowsLogin($user['firma_estado'] ?? null)) {
            $this->repository->revokeCurrent((int) $id, $this->session->id(), 'firma_no_permite_login');

            return false;
        }
        if ($user['firma_id'] !== null) {
            $this->auth->update(['firma_estado' => $user['firma_estado'] ?? null]);
        }

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function mine(): array
    {
        return $this->repository->allForUser((int) $this->auth->id());
    }

    public function revoke(int $sessionId, Request $request): void
    {
        $userId = (int) $this->auth->id();
        if (!$this->repository->revoke($sessionId, $userId, null, 'revocada_por_usuario')) {
            throw new HttpException(404, 'La sesión no existe o ya fue revocada.');
        }
        $this->audit->record('SESION_REVOCADA', 'sesiones', 'user_session', $sessionId, [], $request);
    }

    public function revokeCurrent(string $reason): void
    {
        $id = $this->auth->id();
        if ($id !== null) {
            $this->repository->revokeCurrent((int) $id, $this->session->id(), $reason);
        }
    }
}
