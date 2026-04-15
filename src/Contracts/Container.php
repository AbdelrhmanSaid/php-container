<?php

namespace AbdelrhmanSaid\Container\Contracts;

use ReflectionException;
use Psr\Container\ContainerInterface;
use AbdelrhmanSaid\Container\Errors\NotFoundException;
use AbdelrhmanSaid\Container\Errors\BindingResolutionException;

interface Container extends ContainerInterface
{
    /**
     * Set current container instance.
     *
     * @param \AbdelrhmanSaid\Container\Container $instance
     * @return void
     */
    public static function setInstance(\AbdelrhmanSaid\Container\Container $instance): void;
    

    /**
     * Get current container instance.
     *
     * @return \AbdelrhmanSaid\Container\Container
     */
    public static function getInstance(): \AbdelrhmanSaid\Container\Container;

    /**
     * Bind an abstract to a concrete.
     *
     * @param string $abstract
     * @param string|callable|null $concrete
     * @param bool $shared
     * @return void
     */
    public function bind(string $abstract, string|callable $concrete = null, bool $shared = false): void;

    /**
     * Create a singleton instance of the given class.
     *
     * @param string $abstract
     * @param string|callable|null $concrete
     * @return void
     */
    public function singleton(string $abstract, string|callable $concrete = null): void;

    /**
     * Set an alias for the given abstract.
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void;

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
    public function make(string $abstract, array $params = []): mixed;

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
    public function call(callable|string|array $concrete, array $params = []): mixed;
}
