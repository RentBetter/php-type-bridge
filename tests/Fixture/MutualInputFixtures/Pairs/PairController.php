<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MutualInputFixtures\Pairs;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class PairController
{
    #[Route('/a', methods: ['PUT'])]
    #[ApiRequest(body: AType::class)]
    #[ApiResponses([PairResponse::class])]
    #[McpTool(description: 'Save an A.')]
    public function saveAAction(): PairResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/b', methods: ['PUT'])]
    #[ApiRequest(body: BType::class)]
    #[ApiResponses([PairResponse::class])]
    #[McpTool(description: 'Save a B.')]
    public function saveBAction(): PairResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
