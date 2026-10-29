<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnvBrokenApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes/{id}', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'A note.')]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * A debug endpoint, served in dev alone: reading the routing config for dev fails here.
     */
    #[Route('/debug/notes/{id}', methods: ['GET'], env: 'dev')]
    #[ApiResponses([NoteResponse::class])]
    public function debugNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
