<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequirementRoutedMcpFixtures\Codes\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\RequirementRoutedMcpFixtures\Codes\Response\CodeResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CodeController
{
    #[Route('/codes/{code}/{page}', requirements: ['code' => '[A-Z]+-[0-9]+', 'page' => '[a-z]+'], methods: ['GET'])]
    #[ApiResponses([CodeResponse::class])]
    #[McpTool(description: 'A code.')]
    public function showCodeAction(): CodeResponse
    {
        throw new \LogicException('Fixture only.');
    }

    /**
     * Routed twice: what is described — its path, and each param's name and requirement — is the
     * first route, its own `{id}` a number, not the `{slug}` the second serves.
     */
    #[Route('/notes/{id}', requirements: ['id' => '[0-9]+'], methods: ['GET'])]
    #[Route('/legacy/{slug}', methods: ['GET'])]
    #[ApiResponses([CodeResponse::class])]
    #[McpTool(description: 'A note.')]
    public function showNoteAction(): CodeResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
