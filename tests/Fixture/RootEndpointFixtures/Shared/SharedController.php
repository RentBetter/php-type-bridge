<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootEndpointFixtures\Shared;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class SharedController
{
    #[Route('/shared', methods: ['GET'])]
    #[ApiResponses([SharedResponse::class])]
    public function listSharedAction(): SharedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
