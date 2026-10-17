<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Routing;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use PTGS\TypeBridge\Tests\Fixture\RequirementRoutedMcpFixtures\Codes\Controller\CodeController;
use PTGS\TypeBridge\Tests\Fixture\RoutingApp\Health\HealthController;
use PTGS\TypeBridge\Tests\Fixture\RoutingApp\Projects\Controller\ProjectController;

final class RoutePathResolverTest extends TestCase
{
    private function resolver(?string $routingFile = 'config/routes.yaml'): RoutePathResolver
    {
        return new RoutePathResolver(__DIR__ . '/../Fixture/RoutingApp', $routingFile);
    }

    public function testPrependsThePrefixFromTheRoutingConfig(): void
    {
        // The method attribute says "/accounts/{accountId}/projects"; routes.yaml adds "/api".
        // Reading the attribute alone is what made path-keyed rules match nothing.
        self::assertSame(
            '/api/accounts/{accountId}/projects',
            $this->resolver()->pathFor(ProjectController::class, 'listAction'),
        );
    }

    public function testResolvesEveryMethodOnAController(): void
    {
        self::assertSame(
            '/api/accounts/{accountId}/projects',
            $this->resolver()->pathFor(ProjectController::class, 'createAction'),
        );
    }

    public function testANamespaceWithNoPrefixKeepsItsDeclaredPath(): void
    {
        // Invokable controllers are keyed by the bare FQCN, matching Symfony's own convention.
        self::assertSame('/health', $this->resolver()->pathFor(HealthController::class, '__invoke'));
    }

    public function testAMethodRoutedTwiceResolvesToTheRouteItsAttributeDeclares(): void
    {
        // The route its first #[Route] declares, not the last the collection lists; given the path
        // a #[Route] declares, the route that serves that one.
        $resolver = new RoutePathResolver(__DIR__ . '/../Fixture/RequirementRoutedMcpFixtures', 'config/routes.yaml');

        self::assertSame('/notes/{id}', $resolver->pathFor(CodeController::class, 'showNoteAction'));
        self::assertSame('[0-9]+', $resolver->routeFor(CodeController::class, 'showNoteAction')?->getRequirement('id'));
        self::assertSame('/legacy/{slug}', $resolver->pathFor(CodeController::class, 'showNoteAction', '/legacy/{slug}'));
    }

    public function testUnknownMethodsResolveToNull(): void
    {
        self::assertNull($this->resolver()->pathFor(ProjectController::class, 'notARoute'));
    }

    public function testOnlyInvokeInheritsAnInvokableControllersRoute(): void
    {
        // The bare-class key is how Symfony records an invokable controller. Letting every
        // method fall back to it made a constructor look like a routed API method.
        self::assertNull($this->resolver()->pathFor(HealthController::class, '__construct'));
    }

    public function testResolutionIsDisabledWithoutARoutingFile(): void
    {
        $resolver = $this->resolver(null);

        self::assertNull($resolver->pathFor(ProjectController::class, 'listAction'));
        self::assertNull($resolver->loadError());
    }

    public function testAMissingRoutingFileDegradesRatherThanThrowing(): void
    {
        // A routing file that cannot be read must weaken an optional check, never fail the
        // whole analysis run.
        $resolver = $this->resolver('config/nope.yaml');

        self::assertNull($resolver->pathFor(ProjectController::class, 'listAction'));
        self::assertStringContainsString('was not found', (string) $resolver->loadError());
    }
}
