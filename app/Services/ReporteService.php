<?php

declare(strict_types=1);

namespace App\Services;

final class ReporteService
{
    /** @var array<string, string> */
    private array $permissions = [
        'clientes' => 'clientes.ver',
        'casos' => 'casos.ver',
        'terminos' => 'terminos.ver',
        'audiencias' => 'audiencias.ver',
        'tareas' => 'tareas.ver',
        'documentos' => 'documentos.ver',
        'finanzas' => 'finanzas.ver',
        'auditoria' => 'auditoria.ver',
    ];

    /** @param array<string, mixed> $user @return array<string, string> */
    public function available(array $user): array
    {
        $available = [];
        foreach ($this->permissions as $type => $permission) {
            if ($this->can($user, $permission)) {
                $available[$type] = ucfirst($type);
            }
        }

        return $available;
    }

    /** @param array<string, mixed> $user */
    public function canExport(array $user, string $type): bool
    {
        return isset($this->permissions[$type]) && $this->can($user, $this->permissions[$type]);
    }

    /** @param array<string, mixed> $user */
    private function can(array $user, string $permission): bool
    {
        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
