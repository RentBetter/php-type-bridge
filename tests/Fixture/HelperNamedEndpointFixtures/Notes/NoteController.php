<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\HelperNamedEndpointFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiResponses;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoint `Endpoint`: its result is declared `EndpointResult`, the helper's name.
 */
final class NoteController
{
    #[Route('/notes', methods: ['GET'])]
    #[ApiResponses([NoteResponse::class])]
    public function endpointAction(): NoteResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
