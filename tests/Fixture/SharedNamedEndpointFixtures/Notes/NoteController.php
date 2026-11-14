<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedNamedEndpointFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoints `Money`, named as a config type alias, and `EndpointResult`, as a helper: each
 * declared `export const` beside what the module takes of that name.
 */
final class NoteController
{
    #[Route('/money', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function moneyAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/result', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function endpointResultAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
