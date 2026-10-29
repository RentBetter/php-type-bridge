<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ThrowingRouteFixtures\Notes;

use Symfony\Component\Routing\Attribute\Route;

/**
 * A project's own route attribute, which refuses what it is given.
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class ExplodingRoute extends Route
{
    public function __construct(string $path)
    {
        throw new \InvalidArgumentException(\sprintf('No route for "%s".', $path));
    }
}
