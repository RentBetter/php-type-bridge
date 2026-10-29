<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueQueryAliasFixtures\Prices;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class PriceController
{
    #[Route('/api/prices', methods: ['GET'])]
    #[ApiRequest(query: PriceType::class)]
    #[ApiResponses([PriceResponse::class])]
    public function listPrices(): PriceResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/prices/{id}', methods: ['GET'])]
    #[ApiRequest(path: PricePath::class)]
    #[ApiResponses([PriceResponse::class])]
    public function showPrice(): PriceResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
