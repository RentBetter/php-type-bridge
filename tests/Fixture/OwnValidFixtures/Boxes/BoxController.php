<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class BoxController
{
    #[Route('/boxes', methods: ['POST'])]
    #[ApiRequest(body: BoxType::class)]
    #[ApiResponses([BoxResponse::class])]
    #[McpTool(description: 'Save a box.')]
    public function saveBoxAction(): BoxResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
