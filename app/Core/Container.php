<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Class Container
 * Simple Dependency Injection Container to manage class dependencies.
 */
class Container
{
    /**
     * @var array<string, object|callable>
     */
    private array $services = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Register a service binding.
     *
     * @param string $key
     * @param callable|object $resolver
     */
    public function bind(string $key, $resolver): void
    {
        $this->services[$key] = $resolver;
    }

    /**
     * Register a singleton service.
     *
     * @param string $key
     * @param callable|object $resolver
     */
    public function singleton(string $key, $resolver): void
    {
        $this->bind($key, function ($container) use ($resolver) {
            static $instance;
            if ($instance === null) {
                $instance = $resolver($container);
            }
            return $instance;
        });
    }

    /**
     * Resolve a service instance.
     *
     * @param string $key
     * @return mixed
     * @throws \Exception
     */
    public function get(string $key)
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->services[$key])) {
            $resolver = $this->services[$key];
            
            if (is_callable($resolver)) {
                $instance = $resolver($this);
            } else {
                $instance = $resolver;
            }

            // Save instance if singleton (simplification for this custom container)
            // Ideally we'd have a way to know if a binding is shared or not.
            // For now, we assumption mostly singletons or factories.
            // But let's keep it simple: if resolved from services, we don't cache unless it was a singleton binding logic wrapper.
            // actually, let's trust the resolver.
            return $instance;
        }

        return $this->resolve($key);
    }

    /**
     * Resolve a class using Reflection.
     */
    private function resolve(string $key)
    {
        if (!class_exists($key)) {
            throw new \Exception("Service not found: {$key}");
        }

        $reflector = new \ReflectionClass($key);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$key} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $key();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->getDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve dependencies for a class.
     */
    private function getDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } else {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new \Exception("Cannot resolve dependency '{$parameter->name}'");
                }
            }
        }

        return $dependencies;
    }

    /**
     * Check if a service is bound.
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->services[$key]) || class_exists($key);
    }
}
