<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryFieldContractFixtures\Prices;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class PriceController
{
    #[Route('/prices', methods: ['GET'])]
    #[ApiRequest(query: PriceFilterType::class)]
    #[ApiResponses([PriceResponse::class])]
    #[McpTool(description: 'List prices.')]
    public function listPricesAction(): PriceResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
