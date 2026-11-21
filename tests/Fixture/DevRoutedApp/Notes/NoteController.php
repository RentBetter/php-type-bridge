<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DevRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    /**
     * A tool on a route served in dev alone: production answers 404 at it.
     */
    #[Route('/debug/notes/{id}', methods: ['DELETE'], env: 'dev')]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'Wipe a note.')]
    public function wipeNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
