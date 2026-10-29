<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Loader\DirectoryLoader;
use Symfony\Component\Routing\Loader\GlobFileLoader;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;
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
    /** @var array<string, array<string, non-empty-list<Route>>> by env ('' for none): controller `Class::method` => the routes Symfony serves it at, in the order it loads them */
    private array $routesByController = [];

    private ?string $loadError = null;

    /** @var array<string, string> by env: why the routing config could not be read there */
    private array $envLoadErrors = [];

    /** @var list<string>|null the envs the routing config's `when@` sections name */
    private ?array $configEnvs = null;

    /** @var list<string> the YAML files the routing config was read from with no env */
    private array $resources = [];

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
    public function pathFor(string $className, string $methodName): ?string
    {
        return $this->routeFor($className, $methodName)?->getPath();
    }

    /**
     * The route Symfony serves a controller method at — its path, requirements and options as the
     * routing config leaves them, an import's overriding the attribute's — or null, as pathFor().
     *
     * A method routed more than once is served at each; this is the route its first #[Route]
     * declares, as the attribute loader marks it ({@see ControllerAttributeClassLoader::ATTRIBUTE}),
     * whatever order the router lists them in. A method only routing config routes is served at the
     * first route loaded.
     */
    public function routeFor(string $className, string $methodName): ?Route
    {
        // Loaded with no env, Symfony leaves out a route confined to one, and what the routing
        // config imports in an env alone (`when@prod:`): that is the route it is there — in the envs
        // its #[Route]s name ({@see DeclaredRoutes::envsOf()}), then those the config's sections do.
        foreach (array_unique([null, ...DeclaredRoutes::envsOf($className, $methodName), ...$this->configEnvs()]) as $env) {
            $route = self::routeIn($this->routes($env), $className, $methodName);
            if (null !== $route) {
                return $route;
            }
        }

        return null;
    }

    /**
     * The route a controller method is served at among routes indexed by controller
     * ({@see self::byController()}), as {@see self::routeFor()} picks it.
     *
     * @param array<string, non-empty-list<Route>> $routes
     */
    public static function routeIn(array $routes, string $className, string $methodName): ?Route
    {
        // Symfony keys an invokable controller's route by the bare class name, so that key
        // answers for __invoke and nothing else — a constructor or helper on the same class
        // has no route, and must not inherit one.
        $served = '__invoke' === $methodName
            ? $routes[$className . '::__invoke'] ?? $routes[$className] ?? null
            : $routes[$className . '::' . $methodName] ?? null;
        if (null === $served) {
            return null;
        }

        foreach ($served as $route) {
            if (0 === $route->getOption(ControllerAttributeClassLoader::ATTRIBUTE)) {
                return $route;
            }
        }

        return $served[0];
    }

    /**
     * A #[Route] path as the router serves it, its requirements and defaults with it. One written
     * inline is the route's, not the path's: `{page<\\d+>}` is served as `{page}` holding to `\\d+`,
     * `{slug?intro}` as `{slug}` defaulting to `intro`, and `{slug:note}` as `{slug}` mapped to the
     * `note` argument — Symfony's own Route reads them off. Null for a path or a requirement Symfony
     * refuses.
     *
     * @param array<array-key, mixed> $requirements the attribute's own, which win over inline ones
     */
    public static function declaredRoute(string $path, array $requirements = []): ?Route
    {
        try {
            return new Route($path, requirements: array_filter($requirements, static fn (mixed $requirement, int|string $name): bool => \is_string($name) && \is_string($requirement), \ARRAY_FILTER_USE_BOTH));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A #[Route] path as the router serves it ({@see self::declaredRoute()}); one with nothing
     * written inline — none of the characters Symfony itself looks for — is as it is written.
     */
    public static function declaredPath(string $path): string
    {
        return false === strpbrk($path, '<?:') ? $path : self::declaredRoute($path)?->getPath() ?? $path;
    }

    /**
     * A route path as a client fills it in: an important param, `{!_format}`, is `{_format}` — the
     * `!` says only that every URL the router generates carries it, and a client substituting a
     * param by its name finds it so.
     */
    public static function clientPath(string $path): string
    {
        return preg_replace('/\{!([\w\x80-\xFF]+)\}/', '{$1}', $path) ?? $path;
    }

    /**
     * A route requirement as the router holds it: Symfony's own Route takes off a leading `^` or
     * `\\A` and a trailing `$` or `\\z`. Null for one it refuses.
     */
    public static function servedRequirement(string $requirement): ?string
    {
        return self::declaredRoute('/', ['value' => $requirement])?->getRequirement('value');
    }

    /**
     * Why route resolution is unavailable, for a rule that wants to say so rather than stay quiet:
     * the routing config, read with no env, could not be. One that can't be read in an env alone —
     * a `when@dev` import of a bundle's file, say — leaves every route served without it served
     * ({@see self::envLoadErrors()}).
     */
    public function loadError(): ?string
    {
        $this->routes();

        return $this->loadError;
    }

    /**
     * Why the routing config could not be read in each env it was read in for a route, by env.
     *
     * @return array<string, string>
     */
    public function envLoadErrors(): array
    {
        return $this->envLoadErrors;
    }

    /**
     * The envs the routing config's `when@` sections name, in the files read with no env.
     *
     * @return list<string>
     */
    private function configEnvs(): array
    {
        if (null !== $this->configEnvs) {
            return $this->configEnvs;
        }

        $this->routes();
        $envs = [];
        foreach ($this->resources as $file) {
            try {
                $config = Yaml::parseFile($file);
            } catch (Throwable) {
                continue;
            }
            foreach (\is_array($config) ? array_keys($config) : [] as $key) {
                if (\is_string($key) && str_starts_with($key, 'when@')) {
                    $envs[substr($key, 5)] = true;
                }
            }
        }

        return $this->configEnvs = array_map(strval(...), array_keys($envs));
    }

    /**
     * @return array<string, non-empty-list<Route>>
     */
    private function routes(?string $env = null): array
    {
        if (isset($this->routesByController[$env ?? ''])) {
            return $this->routesByController[$env ?? ''];
        }

        if (null === $this->routingFile) {
            return $this->routesByController[$env ?? ''] = [];
        }

        try {
            $collection = $this->load($env);
        } catch (Throwable $exception) {
            if (null === $env) {
                $this->loadError = $exception->getMessage();
            } else {
                $this->envLoadErrors[$env] = $exception->getMessage();
            }

            return $this->routesByController[$env ?? ''] = [];
        }

        if (null === $env) {
            foreach ($collection->getResources() as $resource) {
                if ($resource instanceof FileResource && 1 === preg_match('/\.ya?ml$/', $resource->getResource())) {
                    $this->resources[] = $resource->getResource();
                }
            }
        }

        return $this->routesByController[$env ?? ''] = self::byController($collection);
    }

    /**
     * The routes by the controller each is served by, its `_controller` default, in the order they
     * are loaded.
     *
     * @return array<string, non-empty-list<Route>>
     */
    public static function byController(RouteCollection $collection): array
    {
        $routes = [];
        foreach ($collection as $route) {
            $controller = $route->getDefault('_controller');
            if (\is_string($controller) && '' !== $controller) {
                $routes[$controller][] = $route;
            }
        }

        return $routes;
    }

    private function load(?string $env): RouteCollection
    {
        $entrypoint = $this->projectDirectory . \DIRECTORY_SEPARATOR . $this->routingFile;
        if (!is_file($entrypoint)) {
            throw new RuntimeException(\sprintf('Routing file "%s" was not found.', $entrypoint));
        }

        $locator = new FileLocator([$this->projectDirectory, \dirname($entrypoint)]);
        $attributeClassLoader = new ControllerAttributeClassLoader($env);

        $resolver = new LoaderResolver([
            new YamlFileLoader($locator, $env),
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
            new GlobFileLoader($locator, $env),
            new DirectoryLoader($locator, $env),
        ]);

        $collection = (new DelegatingLoader($resolver))->load($entrypoint);
        if (!$collection instanceof RouteCollection) {
            throw new RuntimeException(\sprintf('Routing file "%s" did not produce a RouteCollection.', $entrypoint));
        }

        return $collection;
    }
}
