<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    private array $routeParameters = [];

    /** @var array<string, mixed> Atributos de middleware (ej: api_firma_id, api_scopes) */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $json
     * @param array<string, mixed> $files
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $json = [],
        private readonly array $files = [],
        private readonly array $headers = [],
        private readonly array $server = []
    ) {
    }

    public static function capture(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        $rawBody = file_get_contents('php://input');
        $json = [];
        if (is_string($rawBody) && $rawBody !== '' && str_contains(strtolower($headers['content-type'] ?? ''), 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $json['_method'] ?? $headers['x-http-method-override'] ?? null;
            if (is_string($override) && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = strtoupper($override);
            }
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = parse_url($requestUri, PHP_URL_PATH);
        $uri = is_string($uri) && $uri !== '' ? $uri : '/';

        return new self(
            $method,
            self::normalizeUri($uri),
            $_GET,
            $_POST,
            $json,
            $_FILES,
            $headers,
            $_SERVER
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->body : ($this->body[$key] ?? $default);
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->json : ($this->json[$key] ?? $default);
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_replace($this->query, $this->body, $this->json);

        return $key === null ? $input : ($input[$key] ?? $default);
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->headers['user-agent'] ?? '');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) $this->header('accept', ''));

        return $this->isAjax() || str_contains($accept, 'application/json') || str_starts_with($this->uri, '/api/');
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Establece un atributo de request (usado por middleware para pasar contexto a controllers).
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /** @param array<string, string> $parameters */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParameters[$key] ?? $default;
    }

    /** @return array<string, string> */
    public function routeParameters(): array
    {
        return $this->routeParameters;
    }

    private static function normalizeUri(string $uri): string
    {
        $normalized = '/' . trim($uri, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
