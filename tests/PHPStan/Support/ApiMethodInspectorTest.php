<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;
use PTGS\TypeBridge\Routing\RoutePathResolver;
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
}
