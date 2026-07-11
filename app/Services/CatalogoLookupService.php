<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CatalogoRepository;

final class CatalogoLookupService
{
    public function __construct(private readonly CatalogoRepository $repository)
    {
    }

    /** @return list<array{codigo: string, etiqueta: string}> */
    public function items(int $firmaId, string $catalogCode): array
    {
        $items = [];
        foreach ($this->repository->activeItemsByCode($firmaId, $catalogCode) as $item) {
            $items[strtoupper((string) $item['codigo'])] = [
                'codigo' => (string) $item['codigo'],
                'etiqueta' => (string) $item['etiqueta'],
            ];
        }

        return array_values($items);
    }

    public function normalizeRequired(int $firmaId, string $catalogCode, mixed $value, string $label): string
    {
        $normalized = $this->normalizeOptional($firmaId, $catalogCode, $value, $label);
        if ($normalized === null) {
            throw new HttpException(422, $label . ' debe seleccionarse desde el catalogo.');
        }

        return $normalized;
    }

    public function normalizeOptional(int $firmaId, string $catalogCode, mixed $value, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $valueKey = strtoupper($value);
        foreach ($this->items($firmaId, $catalogCode) as $item) {
            if (strtoupper($item['codigo']) === $valueKey || strtoupper($item['etiqueta']) === $valueKey) {
                return (string) $item['codigo'];
            }
        }

        throw new HttpException(422, $label . ' no existe en el catalogo permitido.');
    }
}
