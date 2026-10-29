<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AskedReadFixtures\Fees;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The same body twice: no tool first, where whether Money admits null decides nothing; then a
 * tool, where it does.
 */
final class PriceController
{
    #[Route('/prices/draft', methods: ['PUT'])]
    #[ApiRequest(body: PriceType::class)]
    #[ApiResponses([PriceResponse::class])]
    public function draftPriceAction(): PriceResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/prices', methods: ['PUT'])]
    #[ApiRequest(body: PriceType::class)]
    #[ApiResponses([PriceResponse::class])]
    #[McpTool(description: 'Update a price.')]
    public function updatePriceAction(): PriceResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
