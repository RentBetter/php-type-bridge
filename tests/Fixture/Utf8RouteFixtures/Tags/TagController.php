<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Utf8RouteFixtures\Tags;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A Unicode requirement, the `utf8` option left to the router's default (FrameworkBundle's is true).
 */
final class TagController
{
    #[Route('/tags/{name}', requirements: ['name' => '[\p{L}\d-]+'], methods: ['DELETE'])]
    #[ApiResponses([TagResponse::class])]
    public function deleteTagAction(): TagResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
