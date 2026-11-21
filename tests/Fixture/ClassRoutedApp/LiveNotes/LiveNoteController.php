<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\LiveNotes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes\NoteArchivedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Imported by the routing config in production alone (`when@prod`), and confined there itself.
 */
final class LiveNoteController
{
    #[Route('/notes/{id}', methods: ['GET'], env: 'prod')]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'A live note.')]
    public function showLiveNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
