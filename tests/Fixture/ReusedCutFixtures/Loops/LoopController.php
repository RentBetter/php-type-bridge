<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReusedCutFixtures\Loops;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class LoopController
{
    #[Route('/loops', methods: ['PUT'])]
    #[ApiRequest(body: InputType::class)]
    #[ApiResponses([LoopResponse::class])]
    #[McpTool(description: 'Save a loop.')]
    public function saveLoopAction(): LoopResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
