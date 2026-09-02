<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnloadableFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security\RequiresScope;
use PTGS\TypeBridge\Tests\Fixture\UnloadableFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A perfectly good #[McpTool] endpoint sharing a source tree with a class that cannot
 * load in this install (see DevOnly\DevOnlyRule). Collection must still reach it.
 */
final class PingController
{
    #[Route('/api/ping', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[McpTool(description: 'Ping.')]
    #[RequiresScope('ping:read')]
    public function ping(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
