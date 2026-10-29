<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Throwable;

/**
 * The routes a controller's own attributes declare, read by Symfony's attribute loader as the router
 * reads them: a class-level #[Route]'s path, requirements and methods merged into each method's, and
 * an invokable class's own #[Route] its __invoke's — everything but what a routing config adds, a
 * prefix and an import's overrides. A method's served route says the rest
 * ({@see RoutePathResolver}); without one, this is what the code says it is served at.
 *
 * Read in the env the application is deployed in, as its router is: a route confined to another
 * (`#[Route(env: 'dev')]`) is not served ({@see self::serves()}), though it is still the route the
 * code describes the method by ({@see self::routeFor()}).
 *
 * Each class is read once per env. One Symfony refuses to read — a path or requirement it rejects —
 * has no route, and why is kept for a caller that would rather say so ({@see self::loadError()}).
 */
final class DeclaredRoutes
{
    /** @var array<string, array<string, array<string, non-empty-list<Route>>>> by class and env: its routes by controller */
    private array $routes = [];

    /** @var array<string, string> by class: why its routes could not be read */
    private array $errors = [];

    /** @var array<string, list<string>> by `Class::method`: the envs its routes are confined to, read once */
    private array $envs = [];

    /**
     * @param string $env the env the application is deployed in, as {@see RoutePathResolver::$env}
     */
    public function __construct(
        public readonly string $env = 'prod',
    ) {}

    /**
     * The route the class's attributes declare for the method — the one its first #[Route]
     * declares, as {@see RoutePathResolver::routeFor()} picks a served one — or null when they
     * declare none, or the class can't be read. The one served in the env first; else the one in
     * an env its routes are confined to, which is what the code says the method is, served or not.
     */
    public function routeFor(string $className, string $methodName): ?Route
    {
        $route = $this->routeIn($className, $methodName, $this->env);
        // Its #[Route]s' envs are read only for a method not served in this one.
        foreach (null === $route ? $this->envsOf($className, $methodName) : [] as $env) {
            $route = $env === $this->env ? null : $this->routeIn($className, $methodName, $env);
            if (null !== $route) {
                return $route;
            }
        }

        return $route;
    }

    /**
     * Whether the class's attributes route the method in the env the application is deployed in.
     */
    public function serves(string $className, string $methodName): bool
    {
        return null !== $this->routeIn($className, $methodName, $this->env);
    }

    /**
     * The envs a method's routes are confined to, in the order written: its own #[Route]s', and its
     * class's, which confine every method. Symfony loads such a route only in its env.
     *
     * @return list<string>
     */
    private function envsOf(string $className, string $methodName): array
    {
        return $this->envs[$className . '::' . $methodName] ??= self::readEnvs($className, $methodName);
    }

    /**
     * None where the class's #[Route]s can't be made — their loading said why ({@see self::loadError()}).
     *
     * @return list<string>
     */
    private static function readEnvs(string $className, string $methodName): array
    {
        if (!PhpFileClassLocator::loads($className) || !method_exists($className, $methodName)) {
            return [];
        }

        $class = new ReflectionClass($className);
        $envs = [];
        try {
            foreach ([...$class->getMethod($methodName)->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF), ...$class->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF)] as $attribute) {
                foreach (ControllerAttributeClassLoader::envsOf($attribute->newInstance()) as $env) {
                    $envs[$env] = $env;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return array_values($envs);
    }

    /**
     * Why Symfony refused to read the class's routes, once {@see self::routeFor()} has asked; null
     * when it read them, or the class is one it never routes — abstract, or not a class.
     */
    public function loadError(string $className): ?string
    {
        return $this->errors[$className] ?? null;
    }

    private function routeIn(string $className, string $methodName, string $env): ?Route
    {
        return RoutePathResolver::routeIn($this->routes[$className][$env] ??= $this->read($className, $env), $className, $methodName);
    }

    /**
     * @return array<string, non-empty-list<Route>>
     */
    private function read(string $className, string $env): array
    {
        if (!PhpFileClassLocator::loads($className) || new ReflectionClass($className)->isAbstract()) {
            return [];
        }

        try {
            return RoutePathResolver::byController((new ControllerAttributeClassLoader($env))->load($className));
        } catch (Throwable $exception) {
            $this->errors[$className] ??= $exception->getMessage();

            return [];
        }
    }
}
