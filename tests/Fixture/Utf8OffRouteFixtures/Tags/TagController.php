<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Utf8OffRouteFixtures\Tags;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A Unicode requirement on a route that turns `utf8` off itself: the router refuses to compile it.
 */
final class TagController
{
    #[Route('/tags/{name}', requirements: ['name' => '[\p{L}\d-]+'], options: ['utf8' => false], methods: ['DELETE'])]
    #[ApiResponses([TagResponse::class])]
    public function deleteTagAction(): TagResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
