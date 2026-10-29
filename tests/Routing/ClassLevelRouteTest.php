<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Mcp\McpManifestGenerator;
use PTGS\TypeBridge\Model\CollectedPathParam;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes\AccountNoteController;
use PTGS\TypeBridge\Tests\Fixture\EnvBrokenApp\Notes\NoteController as EnvBrokenNoteController;
use PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes\ArchiveNoteController;
use PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes\EnvNoteController;
use PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes\LabelController;

/**
 * A #[Route] on the class is as much the route as the method's: Symfony serves an invokable
 * controller's __invoke at its class's route, and merges a class's path, requirements and methods
 * into each method's. The HTTP method, the path and its params are the route's, routed or not.
 */
final class ClassLevelRouteTest extends TestCase
{
    private const string APP = __DIR__ . '/../Fixture/ClassRoutedApp';

    /**
     * @return iterable<string, array{TypeBridgeConfig, string}>
     */
    public static function configs(): iterable
    {
        yield 'routed' => [TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], self::APP), '/api'];
        yield 'not routed' => [new TypeBridgeConfig(), ''];
    }

    #[DataProvider('configs')]
    public function testAnInvokableControllerIsServedAtItsClassRoute(TypeBridgeConfig $config, string $prefix): void
    {
        $tool = $this->tools($config)['ArchiveNote'] ?? [];

        self::assertSame('POST', $tool['method'] ?? null);
        self::assertTrue($tool['destructive'] ?? null);
        self::assertSame($prefix . '/notes/{id}/archive', $tool['path'] ?? null);
        self::assertSame(['id'], $tool['inputSchema']['required'] ?? null);
    }

    #[DataProvider('configs')]
    public function testAClassRoutesParamsAreTheMethodsToo(TypeBridgeConfig $config, string $prefix): void
    {
        $tool = $this->tools($config)['DeleteNote'] ?? [];

        self::assertSame('DELETE', $tool['method'] ?? null);
        self::assertSame($prefix . '/accounts/{accountId}/notes/{id}', $tool['path'] ?? null);
        self::assertSame(['accountId', 'id'], $tool['inputSchema']['required'] ?? null);
        // The class's requirement is the param's, as the router holds it.
        self::assertSame(['type' => 'number'], $tool['inputSchema']['properties']['accountId'] ?? null);
    }

    #[DataProvider('configs')]
    public function testAMethodsOwnHttpMethodIsTheOneItIsFor(TypeBridgeConfig $config, string $prefix): void
    {
        // The route serves GET and DELETE, the class's first; the method's own #[Route] says DELETE.
        $tool = $this->tools($config)['RemoveListedNote'] ?? [];

        self::assertSame('DELETE', $tool['method'] ?? null);
        self::assertTrue($tool['destructive'] ?? null);
        self::assertSame($prefix . '/listed-notes/{id}', $tool['path'] ?? null);
    }

    #[DataProvider('configs')]
    public function testARouteConfinedToAnEnvIsTheRouteItIsThere(TypeBridgeConfig $config, string $prefix): void
    {
        // Loaded with no env, Symfony leaves it out; it is the route all the same, where it is served.
        $tools = $this->tools($config);

        self::assertSame('DELETE', $tools['DeleteEnvNote']['method'] ?? null);
        self::assertTrue($tools['DeleteEnvNote']['destructive'] ?? null);
        self::assertSame($prefix . '/env-notes/{id}', $tools['DeleteEnvNote']['path'] ?? null);
        self::assertSame(['id'], $tools['DeleteEnvNote']['inputSchema']['required'] ?? null);
        self::assertSame('PUT', $tools['ProdLabel']['method'] ?? null);
        self::assertSame($prefix . '/prod-labels/{labelId}', $tools['ProdLabel']['path'] ?? null);
        // The routing config is read in that env too: its `when@prod` imports this one, under /api/live.
        self::assertSame(('' === $prefix ? '' : '/api/live') . '/notes/{id}', $tools['ShowLiveNote']['path'] ?? null);
        // And in an env its `when@` sections name, though no #[Route] names one: a controller it
        // imports there alone is served there.
        self::assertSame(('' === $prefix ? '' : '/api/preview') . '/notes/{id}', $tools['ShowPreviewNote']['path'] ?? null);
    }

    #[DataProvider('configs')]
    public function testAnImportantParamIsAParamLikeAnyOther(TypeBridgeConfig $config, string $prefix): void
    {
        $tool = $this->tools($config)['ExportNote'] ?? [];

        // Published as a client fills it: the `!` says only that every URL carries the param.
        self::assertSame($prefix . '/accounts/{accountId}/notes/{id}.{_format}', $tool['path'] ?? null);
        self::assertSame(['accountId', 'id', '_format'], $tool['inputSchema']['required'] ?? null);
    }

    #[DataProvider('configs')]
    public function testAClassRoutesMethodsAreAMethodsThatNamesNone(TypeBridgeConfig $config, string $prefix): void
    {
        $tool = $this->tools($config)['ReplaceLabel'] ?? [];

        self::assertSame('PUT', $tool['method'] ?? null);
        self::assertSame($prefix . '/labels/{labelId}', $tool['path'] ?? null);
    }

    #[DataProvider('configs')]
    public function testTheEndpointIsTheRoutesToo(TypeBridgeConfig $config, string $prefix): void
    {
        $responses = new ResponseClassCollector();
        $enums = new EnumResolver();
        $enums->scanDirectory(self::APP);
        $notes = (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output')))->emit(
            (new PhpDocTypeCollector())->collect(self::APP),
            $responses->collect(self::APP),
            (new EndpointContractCollector(routePathResolver: $config->routePathResolver()))->collect(self::APP, $responses->collectIndex(self::APP)),
        )['Notes'];

        self::assertStringContainsString("export const ArchiveNote: Endpoint<ArchiveNoteEndpointMap, { path: ArchiveNotePathParams }> = {\n  method: 'POST',\n  path: '" . $prefix . "/notes/{id}/archive',\n};", $notes);
        self::assertStringContainsString('export type DeleteNotePathParams = { accountId: number; id: string };', $notes);
        self::assertStringContainsString("  path: '" . $prefix . "/accounts/{accountId}/notes/{id}.{_format}',", $notes);
        self::assertStringContainsString("  method: 'PUT',\n  path: '" . $prefix . "/labels/{labelId}',", $notes);
    }

    public function testAnEnvsRoutingThatCantBeReadFailsNoRouteServedWithoutIt(): void
    {
        // The dev routing imports a bundle's file a standalone reader can't locate; a dev-only endpoint
        // looks there. Every tool the router serves with no env is still served.
        $app = __DIR__ . '/../Fixture/EnvBrokenApp';
        $resolver = new RoutePathResolver($app, 'config/routes.yaml');
        self::assertNull($resolver->pathFor(EnvBrokenNoteController::class, 'debugNoteAction'));
        self::assertNull($resolver->loadError());

        $tools = array_column((new McpManifestGenerator())->generate($app, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $app))['tools'], null, 'name');
        self::assertSame('/api/notes/{id}', $tools['ShowNote']['path'] ?? null);

        // The dev-only endpoint is still described by the route it is in dev, for its TypeScript.
        $responses = new ResponseClassCollector();
        $debug = array_column((new EndpointContractCollector(routePathResolver: $resolver))->collect($app, $responses->collectIndex($app))['Notes'], null, 'methodName')['debugNoteAction'] ?? null;
        self::assertSame(['GET', '/debug/notes/{id}', ['id']], [$debug?->httpMethod, $debug?->httpPath, array_map(static fn(CollectedPathParam $param): string => $param->name, $debug?->request?->pathParams ?? [])]);
    }

    /**
     * @return iterable<string, array{string, TypeBridgeConfig|null}>
     */
    public static function devOnlyTools(): iterable
    {
        $routed = __DIR__ . '/../Fixture/DevRoutedApp';
        $imported = __DIR__ . '/../Fixture/DevImportedApp';
        yield 'confined to dev, routed' => [$routed, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $routed)];
        yield 'confined to dev, not routed' => [$routed, null];
        yield 'imported in dev alone' => [$imported, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $imported)];
        yield 'a dev route of a broken dev config' => [__DIR__ . '/../Fixture/EnvBrokenToolApp', TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], __DIR__ . '/../Fixture/EnvBrokenToolApp')];
    }

    #[DataProvider('devOnlyTools')]
    public function testAToolProductionDoesNotServeIsNoTool(string $app, ?TypeBridgeConfig $config): void
    {
        // Production answers 404 at it: a tool there is worse than none.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('serves no route for it in prod');

        (new McpManifestGenerator())->generate($app, $config ?? new TypeBridgeConfig());
    }

    public function testTheServedEnvIsTheConfigs(): void
    {
        // A project whose deployed env is `dev` serves its dev tools.
        $app = __DIR__ . '/../Fixture/DevRoutedApp';
        $tools = array_column((new McpManifestGenerator())->generate($app, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml', 'routingEnv' => 'dev'], $app))['tools'], null, 'name');

        self::assertSame('/api/debug/notes/{id}', $tools['WipeNote']['path'] ?? null);
    }

    public function testAClassWhoseRoutesSymfonyRefusesIsNoRouteToGuess(): void
    {
        // A requirement with no placeholder name: the router would refuse it, so no method or path is
        // published for it as if it had none.
        $src = __DIR__ . '/../Fixture/BrokenRouteFixtures';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The #[Route]s of PTGS\\TypeBridge\\Tests\\Fixture\\BrokenRouteFixtures\\Notes\\NoteController cannot be read as Symfony reads them: A placeholder name must be a string');

        (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->collectIndex($src));
    }

    public function testTheControllerRulesReadTheRouteTheGeneratorPublishes(): void
    {
        foreach (['/api' => new ApiMethodInspector(new RoutePathResolver(self::APP, 'config/routes.yaml')), '' => new ApiMethodInspector()] as $prefix => $inspector) {
            $archive = $inspector->inspect(ArchiveNoteController::class, '__invoke');
            self::assertSame(['POST'], $archive?->httpMethods);
            self::assertSame($prefix . '/notes/{id}/archive', $archive?->path);

            self::assertSame($prefix . '/accounts/{accountId}/notes/{id}.{_format}', $inspector->inspect(AccountNoteController::class, 'exportNoteAction')?->path);

            self::assertSame(['DELETE'], $inspector->inspect(EnvNoteController::class, 'deleteEnvNoteAction')?->httpMethods);
            self::assertSame($prefix . '/env-notes/{id}', $inspector->inspect(EnvNoteController::class, 'deleteEnvNoteAction')?->path);

            $replace = $inspector->inspect(LabelController::class, 'replaceLabelAction');
            self::assertSame(['PUT'], $replace?->httpMethods);
            self::assertSame($prefix . '/labels/{labelId}', $replace?->path);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tools(TypeBridgeConfig $config): array
    {
        return array_column((new McpManifestGenerator())->generate(self::APP, $config)['tools'], null, 'name');
    }
}
