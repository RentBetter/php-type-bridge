<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CliqueFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class CliqueController
{
    #[Route('/cliques', methods: ['PUT'])]
    #[ApiRequest(body: CliqueInputType::class)]
    #[ApiResponses([CliqueResponse::class])]
    #[McpTool(description: 'Save a clique.')]
    public function saveCliqueAction(): CliqueResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
