<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use Closure;
use RuntimeException;

final class Router
{
    /** @var list<array{methods: list<string>, path: string, regex: string, parameters: list<string>, handler: mixed, middleware: list<mixed>}> */
    private array $routes = [];

    /** @var list<mixed> */
    private array $globalMiddleware = [];

    /** @var list<array{prefix: string, middleware: list<mixed>}> */
    private array $groupStack = [];

    private Closure $controllerResolver;

    private Closure $middlewareResolver;

    public function __construct(callable $controllerResolver, callable $middlewareResolver)
    {
        $this->controllerResolver = Closure::fromCallable($controllerResolver);
        $this->middlewareResolver = Closure::fromCallable($middlewareResolver);
    }

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['PATCH'], $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    /** @param list<string> $methods @param list<mixed> $middleware */
    public function add(array $methods, string $path, mixed $handler, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        $path = $this->normalizePath($prefix . '/' . ltrim($path, '/'));
        [$regex, $parameters] = $this->compilePath($path);

        $this->routes[] = [
            'methods' => array_values(array_unique(array_map('strtoupper', $methods))),
            'path' => $path,
            'regex' => $regex,
            'parameters' => $parameters,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];

        return $this;
    }

    /** @param list<mixed> $middleware */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => $prefix === '/' ? '' : '/' . trim($prefix, '/'),
            'middleware' => $middleware,
        ];

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    public function middleware(mixed $middleware): self
    {
        $this->globalMiddleware[] = $middleware;

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->uri(), $matches)) {
                continue;
            }

            $pathMatched = true;
            if (!in_array($request->method(), $route['methods'], true)) {
                continue;
            }

            $parameters = [];
            foreach ($route['parameters'] as $name) {
                $parameters[$name] = rawurldecode((string) ($matches[$name] ?? ''));
            }
            $request->setRouteParameters($parameters);

            $destination = function (Request $currentRequest) use ($route, $parameters): Response {
                $handler = $this->resolveHandler($route['handler']);
                $result = $handler($currentRequest, ...array_values($parameters));

                if ($result instanceof Response) {
                    return $result;
                }
                if (is_string($result)) {
                    return Response::html($result);
                }

                throw new RuntimeException('La ruta no devolvió una respuesta HTTP válida.');
            };

            $pipeline = array_merge($this->globalMiddleware, $route['middleware']);
            foreach (array_reverse($pipeline) as $definition) {
                $next = $destination;
                $destination = function (Request $currentRequest) use ($definition, $next): Response {
                    $middleware = ($this->middlewareResolver)($definition);
                    if ($middleware instanceof MiddlewareInterface) {
                        return $middleware->handle($currentRequest, $next);
                    }
                    if (is_callable($middleware)) {
                        $response = $middleware($currentRequest, $next);
                        if ($response instanceof Response) {
                            return $response;
                        }
                    }

                    throw new RuntimeException('Middleware no válido.');
                };
            }

            return $destination($request);
        }

        if ($pathMatched) {
            throw new HttpException(405, 'El método HTTP no está permitido para esta ruta.');
        }

        throw new HttpException(404, 'No se encontró el recurso solicitado.');
    }

    private function resolveHandler(mixed $handler): callable
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $controller = ($this->controllerResolver)($handler[0]);
            $handler = [$controller, $handler[1]];
        }

        if (!is_callable($handler)) {
            throw new RuntimeException('Controlador de ruta no válido.');
        }

        return $handler;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @return array{0: string, 1: list<string>} */
    private function compilePath(string $path): array
    {
        $parameters = [];
        $parts = preg_split('/(\{[A-Za-z_][A-Za-z0-9_]*\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException('No fue posible compilar la ruta.');
        }

        $pattern = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $part, $match)) {
                $parameters[] = $match[1];
                $pattern .= '(?P<' . $match[1] . '>[^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return ['#^' . $pattern . '/?$#', $parameters];
    }
}

