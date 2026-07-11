<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $resolving = [];

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function singleton(string $id, callable $factory): void
    {
        $closure = Closure::fromCallable($factory);
        $this->bindings[$id] = function (self $container) use ($id, $closure): mixed {
            if (!array_key_exists($id, $this->instances)) {
                $this->instances[$id] = $closure($container);
            }

            return $this->instances[$id];
        };
    }

    public function bind(string $id, callable $factory): void
    {
        $closure = Closure::fromCallable($factory);
        $this->bindings[$id] = static fn (self $container): mixed => $closure($container);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }

        return $this->autowire($id);
    }

    private function autowire(string $id): object
    {
        if (!class_exists($id)) {
            throw new RuntimeException(sprintf('No existe una definición para "%s".', $id));
        }
        if (isset($this->resolving[$id])) {
            throw new RuntimeException(sprintf('Dependencia circular detectada al resolver "%s".', $id));
        }

        $this->resolving[$id] = true;
        try {
            $reflection = new ReflectionClass($id);
            if (!$reflection->isInstantiable()) {
                throw new RuntimeException(sprintf('La dependencia "%s" no es instanciable.', $id));
            }

            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $arguments = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $arguments[] = $this->get($type->getName());
                    continue;
                }
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException(sprintf('No se pudo resolver el parámetro "%s" de "%s".', $parameter->getName(), $id));
            }

            return $reflection->newInstanceArgs($arguments);
        } finally {
            unset($this->resolving[$id]);
        }
    }
}
