<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Common\Spec\Api;
use PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One #[McpTool] endpoint per place a tool description can come from: the tool's own text,
 * the project's documentation attribute (#[Api], standing in for Spec\Api), and the method's
 * docblock summary. Which one a tool ends up with depends on what the collector is configured
 * to read, so the same controller proves the precedence both with and without the attribute.
 */
final class PingController
{
    /**
     * Count the pings.
     *
     * A second paragraph, which a summary must leave out.
     *
     * @return PingResponse the count
     */
    #[Route('/api/pings/count', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[McpTool]
    public function countPings(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * List the pings, per the docblock.
     */
    #[Route('/api/pings', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[Api(['List the pings.', 'Newest first.'])]
    #[McpTool]
    public function listPings(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/pings/{id}', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[Api('Show one ping, per the API docs.')]
    #[McpTool(description: 'Show one ping, per the tool.')]
    public function showPing(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
