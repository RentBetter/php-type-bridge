<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Support;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Routing\ControllerAttributeClassLoader;
use PTGS\TypeBridge\Routing\DeclaredRoutes;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;

final class ApiMethodInspector
{
    /** What each controller's attributes route it at, read once per class. */
    private readonly DeclaredRoutes $declaredRoutes;

    /**
     * @param RoutePathResolver|null $routePathResolver resolves the *served* path, which the
     *        controller's attributes alone do not give: a routing-config `prefix` is part of it too.
     *        Without one, only the route the attributes declare is known — a class-level #[Route]
     *        merged in — so an application that prefixes in routing config will look like it has no
     *        API routes.
     */
    public function __construct(
        private readonly ?RoutePathResolver $routePathResolver = null,
    ) {
        // Built here, not taken: PHPStan's container autowires every class-typed argument, and one
        // before 2.2 can't build a default written as `new`.
        $this->declaredRoutes = new DeclaredRoutes($routePathResolver->env ?? 'prod');
    }

    /**
     * @param class-string $className
     */
    public function inspect(string $className, string $methodName): ?InspectedApiMethod
    {
        if (!method_exists($className, $methodName)) {
            return null;
        }

        $method = new ReflectionMethod($className, $methodName);

        // The route the generator describes the method by: the one it is served at, else the one its
        // class's attributes declare — a class-level #[Route]'s path and methods merged into its own,
        // an invokable class's its __invoke's. One Symfony's loader reads none of — another
        // framework's attribute, say — is read off the method's attributes as written.
        // Symfony's loader is asked only of a method a #[Route] routes — its own, or for __invoke its
        // class's: the rules inspect every method of every class.
        $routed = self::hasSymfonyRoute($method->getAttributes())
            || ('__invoke' === $methodName && self::hasSymfonyRoute(new ReflectionClass($className)->getAttributes()));
        $route = $this->routePathResolver?->routeFor($className, $methodName) ?? ($routed ? $this->declaredRoutes->routeFor($className, $methodName) : null);
        [$path, $httpMethods] = null !== $route ? [RoutePathResolver::clientPath($route->getPath()), ControllerAttributeClassLoader::methodsFor($route)] : $this->writtenRoute($method);

        $declaredResponses = [];
        $responsesAttributes = $method->getAttributes(ApiResponses::class);
        if ([] !== $responsesAttributes) {
            $arguments = $responsesAttributes[0]->getArguments();
            $responses = $arguments['responses'] ?? ($arguments[0] ?? []);
            if (\is_array($responses)) {
                foreach ($responses as $value) {
                    if (\is_string($value) && '' !== $value) {
                        $declaredResponses[] = $value;
                    }
                }
            }
        }

        return new InspectedApiMethod(
            path: $path,
            httpMethods: $httpMethods,
            hasApiRequest: [] !== $method->getAttributes(ApiRequest::class),
            hasApiResponses: [] !== $responsesAttributes,
            declaredResponses: $declaredResponses,
        );
    }

    /**
     * The path and HTTP methods the method's first route attribute writes, its path as the router
     * would serve it ({@see RoutePathResolver::declaredPath()}).
     *
     * @return array{0: string|null, 1: list<string>}
     */
    private function writtenRoute(ReflectionMethod $method): array
    {
        foreach ($method->getAttributes() as $attribute) {
            if (!$this->isRouteAttribute($attribute)) {
                continue;
            }

            $arguments = $attribute->getArguments();
            $path = $arguments['path'] ?? ($arguments[0] ?? null);
            $methods = $arguments['methods'] ?? [];

            return [
                \is_string($path) ? RoutePathResolver::clientPath(RoutePathResolver::declaredPath($path)) : null,
                \is_array($methods) ? array_values(array_unique(array_map(
                    static fn (mixed $value): string => strtoupper((string) $value),
                    array_filter($methods, static fn (mixed $value): bool => \is_string($value) && '' !== $value),
                ))) : [],
            ];
        }

        return [null, []];
    }

    /**
     * @param list<ReflectionAttribute<object>> $attributes
     */
    private static function hasSymfonyRoute(array $attributes): bool
    {
        return array_any($attributes, static fn (ReflectionAttribute $attribute): bool => self::isSymfonyRoute($attribute->getName()));
    }

    /**
     * Whether the attribute is Symfony's #[Route] or a subclass of it: asked of its name, without
     * loading one whose declaration throws in this install ({@see PhpFileClassLocator::loads()}) —
     * the rules inspect every method, whatever it carries.
     */
    private static function isSymfonyRoute(string $name): bool
    {
        return RouteAttribute::class === $name || (PhpFileClassLocator::loads($name) && is_a($name, RouteAttribute::class, true));
    }

    /**
     * @param ReflectionAttribute<object> $attribute
     */
    private function isRouteAttribute(ReflectionAttribute $attribute): bool
    {
        $name = $attribute->getName();

        return self::isSymfonyRoute($name)
            || str_ends_with($name, '\\Route')
            || 'Route' === $name;
    }
}
