<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnroutedMcpFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\UnroutedMcpFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An #[McpTool] endpoint the routing configuration never imports: it carries a #[Route], but
 * the application serves no path for it. Published as a tool, it would point at a 404.
 */
final class PingController
{
    #[Route('/ping', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[McpTool(description: 'Ping.')]
    public function pingAction(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
