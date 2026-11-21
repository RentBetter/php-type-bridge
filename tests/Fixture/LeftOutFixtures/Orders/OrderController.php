<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LeftOutFixtures\Orders;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController
{
    #[Route('/orders', methods: ['POST'])]
    #[ApiRequest(body: OrderType::class)]
    #[ApiResponses([OrderResponse::class])]
    #[McpTool(description: 'Place an order.')]
    public function createOrderAction(): OrderResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/orders', methods: ['GET'])]
    #[ApiRequest(query: OrderFilterType::class)]
    #[ApiResponses([OrderResponse::class])]
    #[McpTool(description: 'List orders.')]
    public function listOrdersAction(): OrderResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
