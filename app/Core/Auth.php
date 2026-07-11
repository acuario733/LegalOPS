<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const SESSION_KEY = 'auth_user';

    public function __construct(private readonly Session $session)
    {
    }

    public function check(): bool
    {
        $user = $this->user();

        return is_array($user) && isset($user['id']);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $user = $this->session->get(self::SESSION_KEY);

        return is_array($user) ? $user : null;
    }

    public function id(): int|string|null
    {
        return $this->user()['id'] ?? null;
    }

    public function firmaId(): int|string|null
    {
        return $this->user()['firma_id'] ?? null;
    }

    /** @param array<string, mixed> $user */
    public function login(array $user): void
    {
        $this->session->regenerate(true);
        $this->session->put(self::SESSION_KEY, $user);
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): void
    {
        $user = $this->user();
        if ($user === null) {
            return;
        }
        $this->session->put(self::SESSION_KEY, array_replace($user, $attributes));
    }

    public function logout(): void
    {
        $this->session->destroy();
    }
}
