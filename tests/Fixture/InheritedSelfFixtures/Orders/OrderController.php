<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController
{
    #[Route('/orders', methods: ['PUT'])]
    #[ApiRequest(body: OrderType::class)]
    #[ApiResponses([OrderResponse::class])]
    #[McpTool(description: 'Save an order.')]
    public function saveOrderAction(): OrderResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
