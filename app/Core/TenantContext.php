<?php

declare(strict_types=1);

namespace App\Core;

final class TenantContext
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function id(): int
    {
        $firmaId = $this->auth->firmaId();
        if (!is_int($firmaId) && !(is_string($firmaId) && ctype_digit($firmaId))) {
            throw new HttpException(403, 'No existe una firma válida en la sesión.');
        }

        return (int) $firmaId;
    }
}
