<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Form\SaveSpecType;
use PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Response\SpecResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SpecController
{
    #[Route('/api/specs', methods: ['POST'])]
    #[ApiRequest(body: SaveSpecType::class)]
    #[ApiResponses([SpecResponse::class])]
    #[McpTool(description: 'Save a spec.')]
    public function saveSpec(): SpecResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
