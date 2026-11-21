<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A class-level #[Route] that prefixes each method's path with a param of its own: Symfony serves a
 * method's route with the class's path and requirements merged into its own.
 */
#[Route('/accounts/{accountId}', requirements: ['accountId' => '[0-9]+'])]
final class AccountNoteController
{
    #[Route('/notes/{id}', methods: ['DELETE'])]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Delete a note.')]
    public function deleteNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * An important param, `{!_format}`: one every URL gives, read as Symfony's compiler reads it.
     */
    #[Route('/notes/{id}.{!_format}', methods: ['GET'])]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Export a note.')]
    public function exportNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
