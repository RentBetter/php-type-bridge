<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The concrete {@see AttributeClassLoader} a standalone package needs.
 *
 * Symfony ships this as `AttributeRouteControllerLoader` inside FrameworkBundle, which is not a
 * dependency here, so the one abstract method is implemented in the same way: stamp the route with
 * the `_controller` default. That default is the key everything downstream indexes by.
 *
 * Each route also says which of its method's #[Route]s declared it ({@see self::ATTRIBUTE}), so
 * the one the first declares is found as such, whatever order the router lists them in — a
 * priority sorts a later one first — and however its path was written.
 */
final class ControllerAttributeClassLoader extends AttributeClassLoader
{
    /**
     * The option naming which of its method's #[Route]s a route was declared by, counted from 0 in
     * the order they are written.
     */
    public const string ATTRIBUTE = 'typebridge_route_attribute';

    /** @var array<string, int> by `Class::method`, the last of its #[Route]s read in this load */
    private array $read = [];

    private int $reading = 0;

    public function load(mixed $class, ?string $type = null): RouteCollection
    {
        $this->read = [];

        return parent::load($class, $type);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param array<string, mixed>    $globals
     */
    protected function addRoute(RouteCollection $collection, object $attr, array $globals, ReflectionClass $class, ReflectionMethod $method): void
    {
        // Counted whether or not it is served — one confined to another env adds no route — so the
        // count is its place among the method's attributes.
        $key = $class->getName() . '::' . $method->getName();
        $this->reading = $this->read[$key] = ($this->read[$key] ?? -1) + 1;

        parent::addRoute($collection, $attr, $globals, $class, $method);
    }

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
        $route->setOption(self::ATTRIBUTE, $this->reading);
    }
}
