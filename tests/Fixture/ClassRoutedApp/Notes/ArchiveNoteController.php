<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An invokable controller routed by the #[Route] on its class: Symfony serves its __invoke there,
 * a POST taking the note's id.
 */
#[Route('/notes/{id}/archive', methods: ['POST'])]
final class ArchiveNoteController
{
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Archive a note.')]
    public function __invoke(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
