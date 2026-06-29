<?php

declare(strict_types=1);

namespace App\Services;

final class SensitiveDataService
{
    public function maskDocument(?string $value): ?string
    {
        return $this->mask($value, 2, 2);
    }

    public function maskProfessionalCard(?string $value): ?string
    {
        return $this->mask($value, 2, 3);
    }

    public function hash(?string $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return hash('sha256', mb_strtoupper($normalized));
    }

    private function mask(?string $value, int $visibleStart, int $visibleEnd): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }

        $length = mb_strlen($clean);
        if ($length <= ($visibleStart + $visibleEnd)) {
            return mb_substr($clean, 0, 1) . str_repeat('*', max(1, $length - 1));
        }

        return mb_substr($clean, 0, $visibleStart)
            . str_repeat('*', max(4, $length - $visibleStart - $visibleEnd))
            . mb_substr($clean, -$visibleEnd);
    }
}
