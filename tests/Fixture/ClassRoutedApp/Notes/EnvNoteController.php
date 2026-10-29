<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRoutedApp\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A route confined to an env: Symfony serves it only there, and that is the route it is.
 */
final class EnvNoteController
{
    #[Route('/env-notes/{id}', methods: ['DELETE'], env: 'prod')]
    #[ApiResponses([NoteArchivedResponse::class])]
    #[McpTool(description: 'Delete a note, in production.')]
    public function deleteEnvNoteAction(): NoteArchivedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
