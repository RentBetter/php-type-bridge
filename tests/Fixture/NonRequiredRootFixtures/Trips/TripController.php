<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NonRequiredRootFixtures\Trips;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class TripController
{
    #[Route('/trips', methods: ['POST'])]
    #[ApiRequest(body: TripType::class)]
    #[ApiResponses([TripResponse::class])]
    #[McpTool(description: 'Save a trip.')]
    public function saveTripAction(): TripResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
