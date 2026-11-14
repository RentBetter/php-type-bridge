<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootEndpointFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class NotesController
{
    #[Route('/notes', methods: ['GET'])]
    #[ApiResponses([NotesResponse::class])]
    public function listNotesAction(): NotesResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
