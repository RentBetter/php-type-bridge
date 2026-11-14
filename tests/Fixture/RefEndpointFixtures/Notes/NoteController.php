<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RefEndpointFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoint `Ref`, named as the expanding helpers' key: the module declares them all beside
 * it, rather than import `Ref` from the root module.
 */
final class NoteController
{
    #[Route('/ref', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function refAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
