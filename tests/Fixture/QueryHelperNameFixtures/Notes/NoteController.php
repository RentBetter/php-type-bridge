<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryHelperNameFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

final class NoteController
{
    #[Route('/notes', methods: ['GET'])]
    #[ApiRequest(query: NoteFilterType::class)]
    #[ApiResponses([NoteListResponse::class])]
    public function listNotesAction(): NoteListResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
