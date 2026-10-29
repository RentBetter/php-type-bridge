<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CascadeFixtures\Ships;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class ShipController
{
    #[Route('/ships', methods: ['POST'])]
    #[ApiRequest(body: ShipType::class)]
    #[ApiResponses([ShipResponse::class])]
    #[McpTool(description: 'Save a ship.')]
    public function saveShipAction(): ShipResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
