<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SelfNamingInputFixtures\Nodes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NodeController
{
    #[Route('/nodes', methods: ['POST'])]
    #[ApiRequest(body: NodeInputType::class)]
    #[ApiResponses([NodeResponse::class])]
    #[McpTool(description: 'Add a node.')]
    public function createNodeAction(): NodeResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
