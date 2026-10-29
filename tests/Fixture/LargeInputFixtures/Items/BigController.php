<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LargeInputFixtures\Items;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class BigController
{
    #[Route('/bigs', methods: ['PUT'])]
    #[ApiRequest(body: BigType::class)]
    #[ApiResponses([BigResponse::class])]
    #[McpTool(description: 'Save a big.')]
    public function saveBigAction(): BigResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
