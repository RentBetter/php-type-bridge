<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A class-level #[Route] naming a method, and a method naming another: Symfony serves the route at
 * both, the class's first. The method says which it is for.
 */
#[Route('/listed-notes', methods: ['GET'])]
final class ListedNoteController
{
    #[Route('/{id}', methods: ['DELETE'])]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Remove a listed note.')]
    public function removeListedNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
