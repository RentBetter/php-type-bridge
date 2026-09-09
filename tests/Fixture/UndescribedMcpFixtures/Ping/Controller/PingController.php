<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UndescribedMcpFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\UndescribedMcpFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An #[McpTool] endpoint with nothing to describe it: no text on the attribute, no
 * documentation attribute, no docblock on the method. Collection must fail rather than
 * publish a tool the model cannot read.
 */
final class PingController
{
    #[Route('/api/ping', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[McpTool]
    public function ping(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
