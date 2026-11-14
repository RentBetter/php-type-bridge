<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootHelperClashFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function listNotesAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
