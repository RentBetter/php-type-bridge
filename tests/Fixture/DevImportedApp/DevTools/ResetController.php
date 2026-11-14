<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\DevImportedApp\DevTools;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\DevImportedApp\Notes\NoteResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Imported by the routing config in dev alone (`when@dev`): production serves none of it.
 */
final class ResetController
{
    #[Route('/reset', methods: ['POST'])]
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'Reset the dev data.')]
    public function resetAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
