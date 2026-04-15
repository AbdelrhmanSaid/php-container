<?php

namespace AbdelrhmanSaid\Container;

use ReflectionNamedType;
use ReflectionParameter;
use JetBrains\PhpStorm\Pure;
use AbdelrhmanSaid\Container\Contracts\Utils as UtilsContract;

class Utils implements UtilsContract
{
    /**
     * Get the class name of the given object / class.
     *
     * @param ReflectionParameter $parameter
     * @return string|null
     */
    #[Pure] public static function getParameterClassName(ReflectionParameter $parameter): string|null
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) return null;

        $name = $type->getName();

        if (!is_null($class = $parameter->getDeclaringClass())) {
            if ($name === 'self') return $class->getName();
            if ($name === 'parent' && $parent = $class->getParentClass()) return $parent->getName();
        }

        return $name;
    }
}
