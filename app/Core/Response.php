<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;
use RuntimeException;
use stdClass;

final class Response
{
    /** @var array<string, string> */
    private array $headers;

    private ?string $filePath = null;

    private bool $sent = false;

    /** @param array<string, string> $headers */
    private function __construct(
        private string $content,
        private int $status,
        array $headers = []
    ) {
        $this->headers = $headers;
    }

    /** @param array<string, string> $headers */
    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    /**
     * @param array<string, mixed> $errors
     * @param array<string, string> $headers
     * @throws JsonException
     */
    public static function json(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200,
        array $errors = [],
        bool $ok = true,
        array $headers = []
    ): self {
        $payload = [
            'ok' => $ok,
            'message' => $message,
            'data' => $data ?? new stdClass(),
            'errors' => $errors === [] ? new stdClass() : $errors,
        ];

        $content = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self($content, $status, ['Content-Type' => 'application/json; charset=UTF-8'] + $headers);
    }

    /** @param array<string, mixed> $errors */
    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        return self::json(null, $message, $status, $errors, false);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new RuntimeException('Código de redirección no válido.');
        }

        return new self('', $status, ['Location' => $location]);
    }

    public static function download(string $path, string $downloadName, string $mime = 'application/octet-stream'): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new HttpException(404, 'El archivo solicitado no está disponible.');
        }

        $safeName = str_replace(["\r", "\n", '"'], '', basename($downloadName));
        $response = new self('', 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $safeName),
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->filePath = $path;

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        if (str_contains($name, "\r") || str_contains($name, "\n") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('Header HTTP no válido.');
        }

        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        if ($this->filePath !== null) {
            readfile($this->filePath);
        } else {
            echo $this->content;
        }

        $this->sent = true;
    }
}

