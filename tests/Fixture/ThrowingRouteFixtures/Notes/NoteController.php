<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ThrowingRouteFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;

final class NoteController
{
    #[ExplodingRoute('/notes/{id}')]
    #[ApiResponses([NoteResponse::class])]
    public function deleteNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
