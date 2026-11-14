<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NoRouteToolFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;

/**
 * A tool whose route, if it has one, is written somewhere TypeBridge isn't told of: YAML, say.
 */
final class NoteController
{
    #[ApiResponses([NoteResponse::class])]
    #[McpTool(description: 'List notes.')]
    public function listNotesAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
