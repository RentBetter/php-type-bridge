<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RoutedMcpFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\RoutedMcpFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An #[McpTool] endpoint whose served path is not the one on its attribute: routes.yaml
 * prefixes this namespace with `/api`, so the application answers `/api/ping`, not `/ping`.
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
