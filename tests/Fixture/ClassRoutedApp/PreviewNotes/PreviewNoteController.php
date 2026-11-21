<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\PreviewNotes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes\NoteArchivedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Imported by the routing config in production alone (`when@prod`), though its #[Route] names no env.
 */
final class PreviewNoteController
{
    #[Route('/notes/{id}', methods: ['GET'])]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'A preview of a note.')]
    public function showPreviewNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
