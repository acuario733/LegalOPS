<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class TicketValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateTicket(array $data): bool
    {
        return $this->validate($data, [
            'asunto' => 'required|min:5|max:180',
            'categoria' => 'max:80',
            'prioridad' => 'required|enum:baja,media,alta,critica',
            'mensaje' => 'required|min:5|max:4000',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateMessage(array $data): bool
    {
        return $this->validate($data, [
            'mensaje' => 'required|min:2|max:4000',
            'visibilidad' => 'required|enum:firma,superadmin',
        ]);
    }

    /** @param array<string, mixed> $data */
    public function validateStatus(array $data): bool
    {
        return $this->validate($data, [
            'estado' => 'required|enum:abierto,en_proceso,esperando_cliente,cerrado',
        ]);
    }
}
