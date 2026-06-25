<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Permission
{
    private ?Closure $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    /** @param array<string, mixed>|null $user */
    public function allows(string $permission, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        $permissions = $this->all($user);
        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        [$module] = array_pad(explode('.', $permission, 2), 2, null);

        return in_array($module . '.*', $permissions, true);
    }

    /** @param array<string, mixed> $user @return list<string> */
    public function all(array $user): array
    {
        if ($this->resolver !== null) {
            $resolved = ($this->resolver)($user);

            return $this->normalize(is_array($resolved) ? $resolved : []);
        }

        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        foreach ((array) ($user['roles'] ?? []) as $role) {
            if (is_array($role) && is_array($role['permissions'] ?? null)) {
                $permissions = array_merge($permissions, $role['permissions']);
            }
        }

        return $this->normalize($permissions);
    }

    /** @param array<mixed> $permissions @return list<string> */
    private function normalize(array $permissions): array
    {
        $normalized = array_filter(array_map(
            static fn (mixed $permission): string => is_string($permission) ? trim($permission) : '',
            $permissions
        ));

        return array_values(array_unique($normalized));
    }
}

