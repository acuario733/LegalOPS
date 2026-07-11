<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        private readonly int $status,
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function errors(): array
    {
        return $this->errors;
    }
}
