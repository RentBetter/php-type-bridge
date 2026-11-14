<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootQueryNamedFixtures\Shared;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoint `Include` takes a query form, declared `IncludeQuery`; it sends no body, so it
 * takes no include query itself — another module's endpoint does.
 */
final class SharedController
{
    #[Route('/shared', methods: ['GET'])]
    #[ApiRequest(query: SharedFilterType::class)]
    #[ApiResponses([SharedResponse::class])]
    public function includeAction(): SharedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
