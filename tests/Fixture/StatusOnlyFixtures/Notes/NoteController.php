<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\StatusOnlyFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/api/notes/{id}', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'A note.')]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
