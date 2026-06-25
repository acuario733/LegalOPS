<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class DocumentoVersionValidator extends Validator
{
    /** @param array<string, mixed> $data */
    public function validateMetadata(array $data): bool
    {
        return $this->validate($data, [
            'nombre_original' => 'required|min:1|max:255',
            'extension' => 'required|max:20',
            'mime_detectado' => 'required|max:120',
            'size_bytes' => 'required|integer',
            'checksum_sha256' => 'required|max:64',
        ]);
    }
}
