<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(
        protected readonly View $views,
        protected readonly Session $session,
        protected readonly Auth $auth,
        protected readonly Permission $permissions,
        protected readonly Csrf $csrf,
        protected readonly Container $container
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'app', int $status = 200): Response
    {
        return Response::html($this->views->render($view, $data, $layout), $status);
    }

    /** @param array<string, mixed> $errors */
    protected function json(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200,
        array $errors = [],
        bool $ok = true
    ): Response {
        return Response::json($data, $message, $status, $errors, $ok);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }

    /** @return array<string, mixed>|null */
    protected function currentUser(): ?array
    {
        return $this->auth->user();
    }

    protected function currentFirma(): int|string|null
    {
        return $this->auth->firmaId();
    }

    protected function requirePermission(string $permission): void
    {
        if (!$this->permissions->allows($permission, $this->auth->user())) {
            throw new HttpException(403, 'No tiene permiso para realizar esta acción.');
        }
    }

    /** @param array<string, mixed> $errors */
    protected function fail(string $message, int $status = 400, array $errors = []): Response
    {
        return Response::error($message, $status, $errors);
    }
}
