# PHP Container

[![tests](https://github.com/AbdelrhmanSaid/php-container/actions/workflows/php.yml/badge.svg)](https://github.com/AbdelrhmanSaid/php-container/actions/workflows/php.yml)

*Minimal, Lightweight, and Fast PHP Dependency Injection Container*

## Installation

```sh
composer require abdelrhmansaid/container
```

## Testing

```sh
composer test
```

## Usage

The methodologies of the container are based on the [Dependency Injection](https://en.wikipedia.org/wiki/Dependency_injection) paradigm, using the [`ReflectionClass`](https://php.net/manual/en/class.reflectionclass.php) to get class dependencies.

To use the container, you must first create a new instance of the container.

```php
use AbdelrhmanSaid\Container\Container;

$container = new Container();
```

Or just use the static method `Container::getInstance()`, that will return the globally available container if it exists, or create a new one.

```php
$container = Container::getInstance();
```

After you have created the container, you can bind your dependencies to the container.

```php
$container->bind(Foo::class);
```

Also, you can create a singleton, that will be returned every time you call the `get` method.

```php
$container->singleton('foo', function () {
    // ...
});
```

*Singletons are useful for classes that are expensive to instantiate, but only need to be created once.*

To get a dependency from the container, you can call the `make` method.

```php
$foo = $container->make('foo');
```

The main difference between `make` and `get` is that `make` accepts a second parameter, which is an array of parameters to pass to the constructor of the class, while `get` does not *because of implementing PSR-11*

By the way you can also create an alias for a class, so you can call it with a different name.

```php
$container->alias(Foo::class, 'bar');
```

## Auto-wiring

Don't worry about the auto-wiring, the container will do it for you.

```php
$container->make(BrandNewClass::class);
```

It will automatically bind the dependencies of the class, and if the class has a constructor, it will pass the dependencies to it, also the container can inject specific method dependencies using the `call` method.

```php
$container->call([Foo::class, 'setBar'], ['bar' => $bar]);
```

And that's it! Enjoy ✌.
