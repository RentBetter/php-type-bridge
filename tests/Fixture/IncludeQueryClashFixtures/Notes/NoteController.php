<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IncludeQueryClashFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes', methods: ['GET'])]
    #[ApiResponses([ShowNoteResponse::class])]
    public function showNoteAction(): ShowNoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
