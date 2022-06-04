<?php

use Redot\Container\Container;

test('Container::getInstance() returns the current container instance', function () {
    $container = Container::getInstance();
    expect($container)->toBeInstanceOf(Container::class);
});

test('Container::setInstance() sets the current container instance', function () {
    $container = new Container();
    Container::setInstance($container);
    expect(Container::getInstance())->toBe($container);
});

test('Container::bind() binds an abstract to a concrete', function () {
    $container = new Container();
    $container->bind('foo', fn () => 'bar');
    expect($container->get('foo'))->toBe('bar');

    $container->bind('bar', fn ($container, $foo = 'bar') => $foo);
    expect($container->get('bar'))->toBe('bar');

    $container->bind('baz', fn ($container, $foo) => $foo);
    expect($container->make('baz', ['foo' => 'bar']))->toBe('bar');
});

test('Container::singleton() binds an abstract to a concrete and makes it a singleton', function () {
    $container = new Container();
    $stdClass = new stdClass();

    $container->singleton('foo', fn () => $stdClass);
    expect($container->get('foo'))->toBe($stdClass);
});

test('Container::alias() sets an alias for an abstract', function () {
    $container = new Container();
    $container->bind('foo', fn () => 'bar');
    $container->alias('foo', 'bar');
    expect($container->get('foo'))->toBe($container->get('bar'));
});

test('Container::call() calls a callable with the given parameters', function () {
    $class = new class
    {
        public function foo($bar = 'bar')
        {
            return $bar;
        }
    };

    $container = new Container();
    expect($container->call([$class, 'foo']))->toBe('bar');
    expect($container->call(fn ($foo) => $foo, ['foo' => 'bar']))->toBe('bar');

    expect($container->call([get_class($class), 'foo']))->toBe('bar');
    expect($container->call([get_class($class), 'foo'], ['bar' => 'foo']))->toBe('foo');

    expect($container->call(get_class($class) . '@foo'))->toBe('foo');
    expect($container->call(get_class($class) . '::foo'))->toBe('bar');
});
