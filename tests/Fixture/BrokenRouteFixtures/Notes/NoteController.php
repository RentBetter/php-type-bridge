<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BrokenRouteFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A requirement with no placeholder name: Symfony's loader refuses the class's routes.
 */
final class NoteController
{
    #[Route('/notes/{id}', requirements: ['[0-9]+'], methods: ['DELETE'])]
    #[ApiResponses([NoteResponse::class])]
    public function deleteNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
