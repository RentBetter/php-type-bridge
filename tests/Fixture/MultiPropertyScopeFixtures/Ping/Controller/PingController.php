<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Ping\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security\FixtureScope;
use PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Common\Security\Authorize;
use PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Ping\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An #[McpTool] endpoint whose scope attribute also carries an entity map — the shape that
 * makes reading every public property wrong.
 */
final class PingController
{
    #[Route('/api/ping', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    #[McpTool(description: 'Ping.')]
    #[Authorize(FixtureScope::PROJECTS_READ, entities: ['projectId' => PingResponse::class], allowAccessToken: true)]
    public function ping(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
