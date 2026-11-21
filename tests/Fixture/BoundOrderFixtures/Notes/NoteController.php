<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BoundOrderFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/a', methods: ['PUT'])]
    #[ApiRequest(body: AType::class)]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'Save an A.')]
    public function saveAAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/b', methods: ['PUT'])]
    #[ApiRequest(body: BType::class)]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'Save a B.')]
    public function saveBAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
