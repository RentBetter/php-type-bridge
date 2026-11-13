<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFanOutFixtures\Stock;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class StockController
{
    #[Route('/stock', methods: ['PUT'])]
    #[ApiRequest(body: StockInputType::class)]
    #[ApiResponses([StockResponse::class])]
    #[McpTool(description: 'Restock.')]
    public function restockAction(): StockResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * The same body, for no tool: only its TypeScript reads it.
     */
    #[Route('/stock/preview', methods: ['POST'])]
    #[ApiRequest(body: StockInputType::class)]
    #[ApiResponses([StockResponse::class])]
    public function previewRestockAction(): StockResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
