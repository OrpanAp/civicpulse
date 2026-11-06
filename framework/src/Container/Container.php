<?php

declare(strict_types=1);

namespace CivicPulse\Framework\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use ReflectionParameter;
use ReflectionException;

final class Container
{
    /**
     * Registered service bindings.
     *
     * @var array<string, array{concrete: string|Closure, shared: bool}>
     */
    private array $bindings = [];

    /**
     * Resolved singleton instances.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Services currently being resolved.
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * Register a transient binding.
     *
     * A new instance is created each time the service is resolved.
     */
    public function bind(
        string $abstract,
        string|Closure|null $concrete = null
    ): void {
        $this->register($abstract, $concrete ?? $abstract, false);
    }

    /**
     * Register a singleton binding.
     *
     * The first resolved instance is reused on subsequent requests.
     */
    public function singleton(
        string $abstract,
        string|Closure|null $concrete = null
    ): void {
        $this->register($abstract, $concrete ?? $abstract, true);
    }

    /**
     * Register an already-created object.
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->validateAbstract($abstract);

        unset($this->bindings[$abstract]);

        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a service.
     */
    public function make(string $abstract): object
    {
        $this->validateAbstract($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (in_array($abstract, $this->resolving, true)) {
            $chain = [...$this->resolving, $abstract];

            throw new ContainerException(
                'Circular dependency detected: '
                    . implode(' -> ', $chain)
            );
        }

        $binding = $this->bindings[$abstract] ?? null;

        $concrete = $binding['concrete'] ?? $abstract;
        $shared = $binding['shared'] ?? false;

        $this->resolving[] = $abstract;

        try {
            if ($concrete instanceof Closure) {
                $object = $concrete($this);
            } elseif ($concrete === $abstract) {
                // No alias: construct the concrete class directly.
                $object = $this->build($concrete);
            } else {
                // Resolve the concrete identifier through the container.
                $object = $this->make($concrete);
            }

            if (!is_object($object)) {
                throw new ContainerException(
                    "Service [{$abstract}] must resolve to an object."
                );
            }

            if ($shared) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Register a binding internally.
     */
    private function register(
        string $abstract,
        string|Closure $concrete,
        bool $shared
    ): void {
        $this->validateAbstract($abstract);

        unset($this->instances[$abstract]);

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * Construct a class and resolve its constructor dependencies.
     */
    private function build(string $concrete): object
    {
        try {
            $reflection = new ReflectionClass($concrete);
        } catch (ReflectionException $exception) {
            throw new ContainerException(
                "Class [{$concrete}] does not exist.",
                previous: $exception
            );
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(
                "Class [{$concrete}] is not instantiable."
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve one constructor parameter.
     */
    private function resolveParameter(
        ReflectionParameter $parameter
    ): mixed {
        $type = $parameter->getType();
        $name = $parameter->getName();

        if ($type instanceof ReflectionUnionType) {
            throw new ContainerException(
                "Cannot automatically resolve union-typed parameter [\${$name}]. "
                    . 'Register an explicit factory or binding.'
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw new ContainerException(
                "Cannot automatically resolve intersection-typed parameter [\${$name}]. "
                    . 'Register an explicit factory or binding.'
            );
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type?->allowsNull()) {
            return null;
        }

        throw new ContainerException(
            "Cannot resolve parameter [\${$name}]. "
                . 'Provide a class type or a default value.'
        );
    }

    /**
     * Validate a service identifier.
     */
    private function validateAbstract(string $abstract): void
    {
        if (trim($abstract) === '') {
            throw new ContainerException(
                'Service identifier cannot be empty.'
            );
        }
    }
}
