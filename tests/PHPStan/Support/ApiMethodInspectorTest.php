<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;
use PTGS\TypeBridge\Routing\RoutePathResolver;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Tests\Fixture\ClassMethodsFixtures\Notes\NoteController as ClassMethodsController;
use PTGS\TypeBridge\Tests\Fixture\OtherRouteApp\NoteController as OtherRouteController;
use PTGS\TypeBridge\Tests\Fixture\ThrowingRouteFixtures\Notes\NoteController as ThrowingRouteController;
use PTGS\TypeBridge\Tests\Fixture\TwiceRoutedApp\Notes\NoteController;

/**
 * What the controller rules read of an API method: the path it is served at, as the generator
 * publishes it, so a rule never checks one path while the Endpoint and tool name another.
 */
final class ApiMethodInspectorTest extends TestCase
{
    public function testAMethodRoutedTwiceIsServedAtItsFirstRoute(): void
    {
        $inspector = new ApiMethodInspector(new RoutePathResolver(__DIR__ . '/../../Fixture/TwiceRoutedApp', 'config/routes.yaml'));

        self::assertSame('/api/notes/{id}', $inspector->inspect(NoteController::class, 'showNoteAction')?->path);
        self::assertSame('/api/pages/{page}/{slug}', $inspector->inspect(NoteController::class, 'showPageAction')?->path);
    }

    public function testItIsTheHttpMethodTheGeneratorPublishes(): void
    {
        // The class's POST is merged in ahead of the method's GET: the method is the GET it says, to
        // the rules as to the Endpoint and the tool.
        $src = __DIR__ . '/../../Fixture/ClassMethodsFixtures';
        $contract = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->collectIndex($src))['Notes'][0];

        self::assertSame('GET', $contract->httpMethod);
        self::assertSame(['GET'], (new ApiMethodInspector())->inspect(ClassMethodsController::class, 'showNoteAction')?->httpMethods);
    }

    public function testARouteAttributeThatThrowsIsReadAsWritten(): void
    {
        // Symfony's loader can't read it: the rules read the attribute as written, as another
        // framework's, rather than fail.
        self::assertSame('/notes/{id}', (new ApiMethodInspector())->inspect(ThrowingRouteController::class, 'deleteNoteAction')?->path);
    }

    public function testAnotherFrameworksRouteIsReadAsWritten(): void
    {
        // Symfony's loader reads none of it: its path and methods are the attribute's own, its path
        // as a client fills it, as the generator publishes one.
        $method = (new ApiMethodInspector())->inspect(OtherRouteController::class, 'exportNoteAction');

        self::assertSame('/notes/{id}.{_format}', $method?->path);
        self::assertSame(['GET'], $method?->httpMethods);
    }
}
