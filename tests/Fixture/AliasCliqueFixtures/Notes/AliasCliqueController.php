<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AliasCliqueFixtures\Notes;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class AliasCliqueController
{
    #[Route('/alias-cliques', methods: ['PUT'])]
    #[ApiRequest(body: AliasCliqueType::class)]
    #[ApiResponses([AliasCliqueResponse::class])]
    #[McpTool(description: 'Save an alias clique.')]
    public function saveAliasCliqueAction(): AliasCliqueResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
