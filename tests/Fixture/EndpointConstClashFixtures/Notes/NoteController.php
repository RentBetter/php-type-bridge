<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EndpointConstClashFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
