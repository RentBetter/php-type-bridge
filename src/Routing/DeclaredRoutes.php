<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use ReflectionClass;
use Symfony\Component\Routing\Route;
use Throwable;

/**
 * The routes a controller's own attributes declare, read by Symfony's attribute loader as the router
 * reads them: a class-level #[Route]'s path, requirements and methods merged into each method's, and
 * an invokable class's own #[Route] its __invoke's — everything but what a routing config adds, a
 * prefix and an import's overrides. A method's served route says the rest
 * ({@see RoutePathResolver}); without one, this is what the code says it is served at.
 *
 * Each class is read once. One Symfony refuses to read — a path or requirement it rejects — has no
 * route, and why is kept for a caller that would rather say so ({@see self::loadError()}).
 */
final class DeclaredRoutes
{
    /** @var array<string, array<string, non-empty-list<Route>>> by class: its routes by controller */
    private array $routes = [];

    /** @var array<string, string> by class: why its routes could not be read */
    private array $errors = [];

    /**
     * The route the class's attributes declare for the method — the one its first #[Route]
     * declares, as {@see RoutePathResolver::routeFor()} picks a served one — or null when they
     * declare none, or the class can't be read.
     */
    public function routeFor(string $className, string $methodName): ?Route
    {
        return RoutePathResolver::routeIn($this->routes[$className] ??= $this->read($className), $className, $methodName);
    }

    /**
     * Why Symfony refused to read the class's routes, once {@see self::routeFor()} has asked; null
     * when it read them, or the class is one it never routes — abstract, or not a class.
     */
    public function loadError(string $className): ?string
    {
        return $this->errors[$className] ?? null;
    }

    /**
     * @return array<string, non-empty-list<Route>>
     */
    private function read(string $className): array
    {
        if (!class_exists($className) || new ReflectionClass($className)->isAbstract()) {
            return [];
        }

        try {
            return RoutePathResolver::byController((new ControllerAttributeClassLoader())->load($className));
        } catch (Throwable $exception) {
            $this->errors[$className] = $exception->getMessage();

            return [];
        }
    }
}
