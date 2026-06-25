<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class View
{
    public function __construct(private readonly string $viewPath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $content = $this->evaluate($this->resolve($view), $data);
        if ($layout === null) {
            return $content;
        }

        return $this->evaluate(
            $this->resolve('layouts/' . $layout),
            array_merge($data, ['content' => $content])
        );
    }

    /** @param array<string, mixed> $data */
    public function partial(string $partial, array $data = []): string
    {
        $path = str_starts_with($partial, 'partials/') ? $partial : 'partials/' . $partial;

        return $this->evaluate($this->resolve($path), $data);
    }

    public static function escape(mixed $value): string
    {
        return e($value);
    }

    private function resolve(string $view): string
    {
        $view = str_replace('.', '/', trim($view, '/'));
        if ($view === '' || str_contains($view, '..')) {
            throw new RuntimeException('Nombre de vista no válido.');
        }

        $path = $this->viewPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('No se encontró la vista "%s".', $view));
        }

        return $path;
    }

    /** @param array<string, mixed> $data */
    private function evaluate(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            include $path;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}

