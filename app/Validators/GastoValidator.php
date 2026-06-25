<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class GastoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'cliente_id' => 'required|integer',
            'caso_id' => 'integer',
            'documento_id' => 'integer',
            'concepto' => 'required|min:3|max:180',
            'categoria' => 'max:100',
            'monto' => 'required|numeric',
            'moneda' => 'required|max:3',
            'fecha_gasto' => 'required|date:Y-m-d',
            'estado' => 'required|enum:registrado,anulado',
            'observaciones' => 'max:2000',
        ]);
    }
}
