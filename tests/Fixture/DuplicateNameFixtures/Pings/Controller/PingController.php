<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DuplicateNameFixtures\Pings\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\DuplicateNameFixtures\Pings\Response\PingResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PingController
{
    #[Route('/api/ping', methods: ['GET'])]
    #[ApiResponses([PingResponse::class])]
    public function pingAction(): PingResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
