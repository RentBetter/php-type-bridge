<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PathClassToolFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes/{id}', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    #[ApiRequest(path: NotePath::class)]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'Show a note.')]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
