<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Form\ListPricesType;
use PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Form\PickPricesType;
use PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Response\PricesResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Two queries of one shape: the first no tool reads, the second a tool's — read after it.
 */
final class PriceController
{
    #[Route('/api/prices', methods: ['GET'])]
    #[ApiRequest(query: ListPricesType::class)]
    #[ApiResponses([PricesResponse::class])]
    public function listPrices(): PricesResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/prices/pick', methods: ['GET'])]
    #[ApiRequest(query: PickPricesType::class)]
    #[ApiResponses([PricesResponse::class])]
    #[McpTool(description: 'Pick prices.')]
    public function pickPrices(): PricesResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
