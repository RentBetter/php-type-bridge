<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A class-level #[Route] that prefixes each method's path with a param of its own, and gives every
 * method its HTTP method unless the method's names one too: Symfony serves a method's route with
 * the class's path, requirements and methods merged into its own.
 */
#[Route('/accounts/{accountId}', requirements: ['accountId' => '[0-9]+'], methods: ['PUT'])]
final class AccountNoteController
{
    #[Route('/notes/{id}', methods: ['DELETE'])]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Delete a note.')]
    public function deleteNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/labels/{labelId}')]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Replace a label.')]
    public function replaceLabelAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
