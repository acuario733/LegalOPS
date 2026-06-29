<?php

declare(strict_types=1);

namespace App\Services;

final class CommercialStatusService
{
    public const ACTIVE = 'activa';
    public const TRIAL = 'prueba';
    public const PAYMENT_DUE = 'pago_vencido';
    public const SUSPENDED = 'suspendida';
    public const CANCELLED = 'cancelada';

    /** @return array<string, array{label: string, allows_login: bool, allows_operation: bool, consequence: string}> */
    public function definitions(): array
    {
        return [
            self::ACTIVE => [
                'label' => 'Activa',
                'allows_login' => true,
                'allows_operation' => true,
                'consequence' => 'Operacion ordinaria permitida bajo limites del plan.',
            ],
            self::TRIAL => [
                'label' => 'Prueba',
                'allows_login' => true,
                'allows_operation' => true,
                'consequence' => 'Operacion ordinaria permitida bajo limites del plan hasta que se cierre o cambie el periodo de prueba.',
            ],
            self::PAYMENT_DUE => [
                'label' => 'Pago vencido',
                'allows_login' => true,
                'allows_operation' => false,
                'consequence' => 'Ingreso permitido para consulta; operaciones ordinarias bloqueadas hasta regularizacion o reactivacion.',
            ],
            self::SUSPENDED => [
                'label' => 'Suspendida',
                'allows_login' => false,
                'allows_operation' => false,
                'consequence' => 'Acceso ordinario y creacion de recursos bloqueados; sesiones activas revocadas.',
            ],
            self::CANCELLED => [
                'label' => 'Cancelada',
                'allows_login' => false,
                'allows_operation' => false,
                'consequence' => 'Acceso ordinario bloqueado; conserva trazabilidad historica.',
            ],
        ];
    }

    public function normalize(mixed $status): string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));

        return match ($normalized) {
            'active' => self::ACTIVE,
            'trial' => self::TRIAL,
            'overdue', 'payment_due' => self::PAYMENT_DUE,
            'suspended' => self::SUSPENDED,
            'cancelled', 'canceled' => self::CANCELLED,
            default => $normalized,
        };
    }

    public function isKnown(mixed $status): bool
    {
        return array_key_exists($this->normalize($status), $this->definitions());
    }

    public function allowsLogin(mixed $status): bool
    {
        $definition = $this->definitions()[$this->normalize($status)] ?? null;

        return $definition !== null && $definition['allows_login'];
    }

    public function allowsOperation(mixed $status): bool
    {
        $definition = $this->definitions()[$this->normalize($status)] ?? null;

        return $definition !== null && $definition['allows_operation'];
    }

    public function label(mixed $status): string
    {
        $normalized = $this->normalize($status);

        return $this->definitions()[$normalized]['label'] ?? 'Estado comercial no reconocido';
    }

    public function consequence(mixed $status): string
    {
        $normalized = $this->normalize($status);

        return $this->definitions()[$normalized]['consequence'] ?? 'El estado comercial no reconocido bloquea la operacion por seguridad.';
    }
}
