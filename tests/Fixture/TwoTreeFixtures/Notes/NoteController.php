<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TwoTreeFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes', methods: ['PUT'])]
    #[ApiRequest(body: NoteInputType::class)]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'Save a note.')]
    public function saveNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
