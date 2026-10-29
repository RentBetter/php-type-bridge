<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueBodyAliasFixtures\Prices;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class PriceController
{
    #[Route('/api/prices', methods: ['PUT'])]
    #[ApiRequest(body: PriceType::class)]
    #[ApiResponses([PriceResponse::class])]
    #[McpTool(description: 'Update a price.')]
    public function updatePrice(): PriceResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
