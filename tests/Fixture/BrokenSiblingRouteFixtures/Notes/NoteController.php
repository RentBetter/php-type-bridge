<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BrokenSiblingRouteFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/** A controller no routing config imports, one of whose #[Route]s Symfony refuses. */
#[Route('/api')]
final class NoteController
{
    #[Route('/notes/{id}', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function showNoteAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/legacy/{id}', requirements: ['id' => ''])]
    public function legacyAction(): void
    {
    }
}
