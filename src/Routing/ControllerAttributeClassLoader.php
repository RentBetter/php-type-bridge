<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;

/**
 * The concrete {@see AttributeClassLoader} a standalone package needs.
 *
 * Symfony ships this as `AttributeRouteControllerLoader` inside FrameworkBundle, which is not a
 * dependency here, so the one abstract method is implemented in the same way: stamp the route with
 * the `_controller` default. That default is the key everything downstream indexes by.
 */
final class ControllerAttributeClassLoader extends AttributeClassLoader
{
    /**
     * @param ReflectionClass<object> $class
     */
    protected function configureRoute(Route $route, ReflectionClass $class, ReflectionMethod $method, object $attr): void
    {
        // Invokable controllers come through as the bare FQCN, matching Symfony's own convention
        // and the shape the kernel puts in the request's `_controller` attribute.
        $route->setDefault(
            '_controller',
            '__invoke' === $method->getName() ? $class->getName() : $class->getName() . '::' . $method->getName(),
        );
    }
}
