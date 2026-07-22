<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MissingScopeFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\MissingScopeFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An #[McpTool] endpoint with no scope attribute anywhere — collection must fail
 * when a scope attribute is configured.
 */
final class PingController
{
    #[Route('/api/ping', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[McpTool(description: 'Ping.')]
    public function ping(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
