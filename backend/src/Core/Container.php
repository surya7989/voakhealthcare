<?php

declare(strict_types=1);

namespace Voak\Health\Core;

use Voak\Health\Core\Exceptions\ConfigurationException;

/**
 * Lightweight PSR-11 compliant Dependency Injection Container.
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function set(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = ['factory' => $factory, 'singleton' => true];
    }

    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = ['factory' => $factory, 'singleton' => false];
    }

    public function get(string $id): object
    {
        // 1. Check existing singleton instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Check registered factory binding
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            $instance = ($binding['factory'])($this);
            if ($binding['singleton']) {
                $this->instances[$id] = $instance;
            }
            return $instance;
        }

        // 3. Fallback: reflection auto-wiring
        if (class_exists($id)) {
            $reflector = new \ReflectionClass($id);
            if (!$reflector->isInstantiable()) {
                throw new ConfigurationException("Class [{$id}] is not instantiable.");
            }

            $constructor = $reflector->getConstructor();
            if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                $instance = $reflector->newInstance();
                return $instance;
            }

            $dependencies = [];
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencies[] = $this->get($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new ConfigurationException("Cannot resolve parameter [{$param->getName()}] for class [{$id}].");
                }
            }

            return $reflector->newInstanceArgs($dependencies);
        }

        throw new ConfigurationException("Service [{$id}] not found in container.");
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }
}
