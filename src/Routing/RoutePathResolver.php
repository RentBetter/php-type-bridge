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
    /** @var array<string, string>|null controller `Class::method` => path */
    private ?array $pathsByController = null;

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
     * configured, or the routing file could not be loaded).
     */
    public function pathFor(string $className, string $methodName): ?string
    {
        $paths = $this->paths();

        return $paths[$className . '::' . $methodName] ?? $paths[$className] ?? null;
    }

    /**
     * Why route resolution is unavailable, for a rule that wants to say so rather than stay quiet.
     */
    public function loadError(): ?string
    {
        $this->paths();

        return $this->loadError;
    }

    /**
     * @return array<string, string>
     */
    private function paths(): array
    {
        if (null !== $this->pathsByController) {
            return $this->pathsByController;
        }

        if (null === $this->routingFile) {
            return $this->pathsByController = [];
        }

        try {
            $collection = $this->load();
        } catch (Throwable $exception) {
            $this->loadError = $exception->getMessage();

            return $this->pathsByController = [];
        }

        $paths = [];
        foreach ($collection as $route) {
            $controller = $route->getDefault('_controller');
            if (\is_string($controller) && '' !== $controller) {
                $paths[$controller] = $route->getPath();
            }
        }

        return $this->pathsByController = $paths;
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
