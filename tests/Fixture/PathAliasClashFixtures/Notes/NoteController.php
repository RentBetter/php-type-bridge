<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PathAliasClashFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes/{noteId}', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function getNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
