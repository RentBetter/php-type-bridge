<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
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

    /**
     * The option holding the HTTP methods a route's own #[Route] names, uppercased: Symfony merges a
     * class-level #[Route]'s in ahead of them, and these say which the method is for.
     */
    public const string METHODS = 'typebridge_route_methods';

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
        $route->setOption(self::METHODS, self::methodsOf($attr));
    }

    /**
     * The HTTP methods a #[Route] names itself, uppercased.
     *
     * @return list<string>
     */
    public static function methodsOf(object $attr): array
    {
        $methods = self::setting($attr, 'methods');

        return array_values(array_map(strtoupper(...), array_filter(\is_array($methods) ? $methods : [], \is_string(...))));
    }

    /**
     * The envs a #[Route] is confined to, if any: Symfony loads it only in one of them.
     *
     * @return list<string>
     */
    public static function envsOf(object $attr): array
    {
        // `envs` from Symfony 7.4, a single `env` before it.
        $envs = property_exists(RouteAttribute::class, 'envs') ? self::setting($attr, 'envs') : self::setting($attr, 'env');

        return array_values(array_filter(\is_array($envs) ? $envs : [$envs], \is_string(...)));
    }

    /**
     * A #[Route]'s own setting, whichever Symfony is installed: a public property from 7.4, a private
     * one behind a getter before it.
     */
    private static function setting(object $attr, string $name): mixed
    {
        return $attr instanceof RouteAttribute && property_exists(RouteAttribute::class, $name)
            ? (new ReflectionProperty(RouteAttribute::class, $name))->getValue($attr)
            : null;
    }
}
