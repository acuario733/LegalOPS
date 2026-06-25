<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class PagoValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateData(array $data): bool
    {
        return $this->validate($data, [
            'cliente_id' => 'required|integer',
            'caso_id' => 'integer',
            'honorario_id' => 'integer',
            'fecha_pago' => 'required|date:Y-m-d',
            'monto' => 'required|numeric',
            'moneda' => 'required|max:3',
            'metodo_pago' => 'required|max:80',
            'referencia' => 'max:180',
            'estado' => 'required|enum:registrado,anulado',
            'observaciones' => 'max:2000',
        ]);
    }
}
