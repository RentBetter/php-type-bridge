<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CrossReadFixtures\Links;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class LinkController
{
    #[Route('/x', methods: ['PUT'])]
    #[ApiRequest(body: XType::class)]
    #[ApiResponses([LinkResponse::class])]
    #[McpTool(description: 'Save an X.')]
    public function saveXAction(): LinkResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/y', methods: ['PUT'])]
    #[ApiRequest(body: YType::class)]
    #[ApiResponses([LinkResponse::class])]
    #[McpTool(description: 'Save a Y.')]
    public function saveYAction(): LinkResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
