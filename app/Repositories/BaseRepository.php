<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

abstract class BaseRepository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }
}
