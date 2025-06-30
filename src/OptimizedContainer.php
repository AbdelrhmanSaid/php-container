<?php

namespace Redot\Container;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use Redot\Container\Errors\NotFoundException;
use Redot\Container\Errors\BindingResolutionException;
use Redot\Container\Contracts\Container as ContainerContract;

class OptimizedContainer implements ContainerContract
{
    /**
     * Globally available container instance.
     *
     * @var \Redot\Container\Container|null
     */
    protected static \Redot\Container\Container|null $instance = null;

    /**
     * Container bindings.
     *
     * @var array
     */
    protected array $bindings = [];

    /**
     * Array of Singletons.
     *
     * @var array
     */
    protected array $instances = [];

    /**
     * Binding aliases.
     *
     * @var array
     */
    protected array $aliases = [];

    /**
     * Resolved instances.
     *
     * @var array
     */
    protected array $resolved = [];

    /**
     * Reflection cache to avoid repeated ReflectionClass instantiation.
     *
     * @var array<string, ReflectionClass>
     */
    protected array $reflectionCache = [];

    /**
     * Flattened alias cache to avoid recursive lookups.
     *
     * @var array<string, string>
     */
    protected array $flattenedAliases = [];

    /**
     * Dependency cache for constructor parameters.
     *
     * @var array<string, array>
     */
    protected array $dependencyCache = [];

    /**
     * Set current container instance.
     *
     * @param \Redot\Container\Container $instance
     * @return void
     */
    public static function setInstance(\Redot\Container\Container $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get current container instance.
     *
     * @return \Redot\Container\Container
     */
    public static function getInstance(): \Redot\Container\Container
    {
        if (is_null(self::$instance)) self::$instance = new OptimizedContainer();
        return self::$instance;
    }

    /**
     * Bind an abstract to a concrete.
     *
     * @param string $abstract
     * @param string|callable|null $concrete
     * @param bool $shared
     * @return void
     */
    public function bind(string $abstract, string|callable $concrete = null, bool $shared = false): void
    {
        if (is_null($concrete)) $concrete = $abstract;
        $this->bindings[$abstract] = compact('concrete', 'shared');
        
        // Clear caches that might be affected
        unset($this->dependencyCache[$abstract]);
        unset($this->reflectionCache[$abstract]);
    }

    /**
     * Create a singleton instance of the given class.
     *
     * @param string $abstract
     * @param string|callable|null $concrete
     * @return void
     */
    public function singleton(string $abstract, string|callable $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Set an alias for the given abstract.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
        // Clear flattened alias cache when new aliases are added
        $this->flattenedAliases = [];
    }

    /**
     * Make a concrete instance of the given abstract.
     *
     * @param string $abstract
     * @param array $params
     * @return mixed
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    public function make(string $abstract, array $params = []): mixed
    {
        $original = $abstract;
        $abstract = $this->getAlias($abstract);
        
        // Fast path for singletons
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);
        
        if ($this->isBuildable($concrete, $abstract)) {
            $obj = $this->build($concrete, $params);
        } else {
            $obj = $this->make($concrete);
        }

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $obj;
        }
        
        $this->resolved[$abstract] = true;

        return $obj;
    }

    /**
     * Get abstract alias with flattened cache for performance.
     *
     * @param string $abstract
     * @return string
     */
    protected function getAlias(string $abstract): string
    {
        // Use cached flattened alias if available
        if (isset($this->flattenedAliases[$abstract])) {
            return $this->flattenedAliases[$abstract];
        }

        $original = $abstract;
        $resolved = $abstract;
        $seen = [];

        // Flatten alias chain and cache result
        while (isset($this->aliases[$resolved])) {
            if (isset($seen[$resolved])) {
                // Circular reference detected
                break;
            }
            $seen[$resolved] = true;
            $resolved = $this->aliases[$resolved];
        }

        // Cache the flattened result
        $this->flattenedAliases[$original] = $resolved;
        return $resolved;
    }

    /**
     * Get abstract concrete.
     *
     * @param string $abstract
     * @return mixed
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Check if concrete can be built.
     *
     * @param callable|string $concrete
     * @param string $abstract
     * @return bool
     */
    protected function isBuildable(callable|string $concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Check if abstract is a singleton.
     *
     * @param string $abstract
     * @return bool
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
            (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Build concrete with cached reflection.
     *
     * @param callable|string $concrete
     * @param array $params
     * @return mixed
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    protected function build(callable|string $concrete, array $params = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, ...array_values($params));
        }

        $reflector = $this->getReflection($concrete);

        if (!$reflector->isInstantiable()) {
            throw new BindingResolutionException("Target [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            return $reflector->newInstance();
        }

        $dependencies = $this->getCachedDependencies($concrete, $constructor, $params);
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Get cached reflection class to avoid repeated instantiation.
     *
     * @param string $concrete
     * @return ReflectionClass
     * @throws NotFoundException
     */
    protected function getReflection(string $concrete): ReflectionClass
    {
        if (!isset($this->reflectionCache[$concrete])) {
            try {
                $this->reflectionCache[$concrete] = new ReflectionClass($concrete);
            } catch (ReflectionException) {
                throw new NotFoundException("Unable to resolve class [$concrete]");
            }
        }

        return $this->reflectionCache[$concrete];
    }

    /**
     * Get dependencies with caching for better performance.
     *
     * @param string $concrete
     * @param ReflectionMethod $constructor
     * @param array $params
     * @return array
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    protected function getCachedDependencies(string $concrete, ReflectionMethod $constructor, array $params = []): array
    {
        $cacheKey = $concrete;
        
        // If we have cached dependencies and no custom params, use cache
        if (empty($params) && isset($this->dependencyCache[$cacheKey])) {
            return $this->dependencyCache[$cacheKey];
        }

        $dependencies = $constructor->getParameters();
        $args = $this->getDependencies($dependencies, $params);

        // Cache only if no custom params were provided
        if (empty($params)) {
            $this->dependencyCache[$cacheKey] = $args;
        }

        return $args;
    }

    /**
     * Get dependencies for the given constructor.
     *
     * @param array $dependencies
     * @param array $params
     * @return array
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    protected function getDependencies(array $dependencies, array $params = []): array
    {
        return array_map(function ($dependency) use ($params) {
            if (isset($params[$dependency->getName()])) {
                return $params[$dependency->getName()];
            }
            if ($dependency->isDefaultValueAvailable()) {
                return $dependency->getDefaultValue();
            }
            return $this->make(Utils::getParameterClassName($dependency));
        }, $dependencies);
    }

    /**
     * Call the given callback with the given parameters.
     *
     * @param callable|string|array $concrete
     * @param array $params
     * @return mixed
     *
     * @throws BindingResolutionException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function call(callable|string|array $concrete, array $params = []): mixed
    {
        if (!is_array($concrete)) {
            $concrete = $this->parseConcrete($concrete);
        }

        [$callback, $method] = $concrete;
        $callback = is_string($callback) ? $this->make($callback) : $callback;

        $reflector = new ReflectionMethod($callback, $method);
        $args = $this->getDependencies($reflector->getParameters(), $params);
        return $reflector->invokeArgs($callback, $args);
    }
    
    /**
     * Resolve all of the bindings.
     *
     * @return void
     */
    public function resolve(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            if (!isset($this->resolved[$abstract])) {
                $this->make($abstract);
            }
        }
    }

    /**
     * Parse concrete to array.
     *
     * @param callable|string $concrete
     * @return array
     */
    protected function parseConcrete(callable|string $concrete): array
    {
        if (is_string($concrete)) {
            if (str_contains($concrete, '@')) return explode('@', $concrete, 2);
            if (str_contains($concrete, '::')) return explode('::', $concrete, 2);
        }

        return [Closure::fromCallable($concrete), '__invoke']; 
    }

    /**
     * Determine if a given type has been bound.
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Clear all caches for memory management.
     *
     * @return void
     */
    public function clearCaches(): void
    {
        $this->reflectionCache = [];
        $this->flattenedAliases = [];
        $this->dependencyCache = [];
    }

    /**
     * Get cache statistics for debugging.
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        return [
            'reflection_cache_size' => count($this->reflectionCache),
            'flattened_aliases_size' => count($this->flattenedAliases),
            'dependency_cache_size' => count($this->dependencyCache),
            'bindings_count' => count($this->bindings),
            'instances_count' => count($this->instances),
            'aliases_count' => count($this->aliases),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }
}