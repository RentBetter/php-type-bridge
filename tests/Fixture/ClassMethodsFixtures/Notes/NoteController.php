<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassMethodsFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The class's POST is merged in ahead of each method's own: `showNoteAction` is served as POST and
 * GET, and is the GET it says it is.
 */
#[Route('/notes', methods: ['POST'])]
final class NoteController
{
    #[Route('/{id}', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
