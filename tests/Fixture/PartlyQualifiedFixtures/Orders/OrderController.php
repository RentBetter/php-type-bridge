<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PartlyQualifiedFixtures\Orders;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController
{
    #[Route('/orders', methods: ['POST'])]
    #[ApiRequest(body: OrderInputType::class)]
    #[ApiResponses([OrderResponse::class])]
    #[McpTool(description: 'Place an order.')]
    public function createOrderAction(): OrderResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
