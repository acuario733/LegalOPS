<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class PortalAutorizacionValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'cliente_id' => 'required|integer',
            'recurso_tipo' => 'required|enum:caso,documento,honorario,pago,gasto,usuario_cliente',
            'recurso_id' => 'required|integer',
            'estado' => 'required|enum:autorizado,revocado',
            'observacion_publica' => 'max:2000',
            'observacion_interna' => 'max:2000',
        ]);
    }
}
