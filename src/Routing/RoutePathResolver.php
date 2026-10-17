<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Loader\DirectoryLoader;
use Symfony\Component\Routing\Loader\GlobFileLoader;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * The real full path of a controller method, resolved by Symfony's own router.
 *
 * A route's path is assembled from parts that do not all live in PHP: a `prefix` in the routing
 * config, an optional class-level #[Route], and the method-level #[Route]. Static analysis can see
 * the attributes but never the config file, so a rule that inspects only the method attribute sees
 * `/accounts/{accountId}/projects` where the application serves `/api/accounts/{accountId}/projects`
 * — and any rule keyed on the real path silently matches nothing.
 *
 * Rather than re-deriving the path from its parts (and drifting from Symfony's own precedence and
 * prefix rules), this loads the actual RouteCollection from the application's routing entrypoint
 * and indexes it by the `_controller` default. Whatever Symfony would serve is what a rule sees.
 *
 * The collection is loaded once and reused; loading is deliberately forgiving, because a routing
 * file that cannot be read must degrade an optional check, never fail the whole analysis.
 */
final class RoutePathResolver
{
    /** @var array<string, non-empty-list<Route>>|null controller `Class::method` => the routes Symfony serves it at, in the order it loads them */
    private ?array $routesByController = null;

    private ?string $loadError = null;

    /**
     * @param string $projectDirectory absolute path the routing entrypoint is resolved against
     * @param string|null $routingFile entrypoint relative to the project directory (e.g. `config/routes.yaml`);
     *        null disables resolution and every lookup returns null
     */
    public function __construct(
        private readonly string $projectDirectory,
        private readonly ?string $routingFile = null,
    ) {}

    /**
     * The served path for a controller method, or null when it has no route (or routing is not
     * configured, or the routing file could not be loaded). Which route, for a method routed more
     * than once: see routeFor().
     */
    public function pathFor(string $className, string $methodName, ?string $declaredPath = null): ?string
    {
        return $this->routeFor($className, $methodName, $declaredPath)?->getPath();
    }

    /**
     * The route Symfony serves a controller method at — its path, requirements and options as the
     * routing config leaves them, an import's overriding the attribute's — or null, as pathFor().
     *
     * A method routed more than once is served at each. Given the path a #[Route] on it declares,
     * this is the route serving that one: the first whose path ends with it, a prefix or a class
     * #[Route] coming before. Otherwise, the first route loaded, which is its first #[Route]'s.
     */
    public function routeFor(string $className, string $methodName, ?string $declaredPath = null): ?Route
    {
        $routes = $this->routes();

        // Symfony keys an invokable controller's route by the bare class name, so that key
        // answers for __invoke and nothing else — a constructor or helper on the same class
        // has no route, and must not inherit one.
        $served = '__invoke' === $methodName
            ? $routes[$className . '::__invoke'] ?? $routes[$className] ?? null
            : $routes[$className . '::' . $methodName] ?? null;
        if (null === $served) {
            return null;
        }

        if (null !== $declaredPath) {
            foreach ($served as $route) {
                if (str_ends_with($route->getPath(), $declaredPath)) {
                    return $route;
                }
            }
        }

        return $served[0];
    }

    /**
     * Why route resolution is unavailable, for a rule that wants to say so rather than stay quiet.
     */
    public function loadError(): ?string
    {
        $this->routes();

        return $this->loadError;
    }

    /**
     * @return array<string, non-empty-list<Route>>
     */
    private function routes(): array
    {
        if (null !== $this->routesByController) {
            return $this->routesByController;
        }

        if (null === $this->routingFile) {
            return $this->routesByController = [];
        }

        try {
            $collection = $this->load();
        } catch (Throwable $exception) {
            $this->loadError = $exception->getMessage();

            return $this->routesByController = [];
        }

        $routes = [];
        foreach ($collection as $route) {
            $controller = $route->getDefault('_controller');
            if (\is_string($controller) && '' !== $controller) {
                $routes[$controller][] = $route;
            }
        }

        return $this->routesByController = $routes;
    }

    private function load(): RouteCollection
    {
        $entrypoint = $this->projectDirectory . \DIRECTORY_SEPARATOR . $this->routingFile;
        if (!is_file($entrypoint)) {
            throw new RuntimeException(\sprintf('Routing file "%s" was not found.', $entrypoint));
        }

        $locator = new FileLocator([$this->projectDirectory, \dirname($entrypoint)]);
        $attributeClassLoader = new ControllerAttributeClassLoader();

        $resolver = new LoaderResolver([
            new YamlFileLoader($locator),
            new Psr4DirectoryLoader($locator),
            // The class loader must precede the directory loader. Psr4DirectoryLoader resolves a
            // directory to class names and re-imports each one, and AttributeDirectoryLoader
            // claims *any* string when the type is "attribute" — including a class name, which it
            // then tries to locate as a file. Ordering it after the class loader is what keeps a
            // PSR-4 import working; its own supports() only matches a class-name shape, so it
            // never steals a real path.
            $attributeClassLoader,
            new AttributeDirectoryLoader($locator, $attributeClassLoader),
            new AttributeFileLoader($locator, $attributeClassLoader),
            new GlobFileLoader($locator),
            new DirectoryLoader($locator),
        ]);

        $collection = (new DelegatingLoader($resolver))->load($entrypoint);
        if (!$collection instanceof RouteCollection) {
            throw new RuntimeException(\sprintf('Routing file "%s" did not produce a RouteCollection.', $entrypoint));
        }

        return $collection;
    }
}
