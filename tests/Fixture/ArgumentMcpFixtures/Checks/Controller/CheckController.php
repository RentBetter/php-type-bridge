<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Form\ListChecksFilterType;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Form\RecordVerdictType;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Response\CheckResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Two tools whose arguments are worth more than their types: a filter and a verdict, each
 * taking values from a known set.
 */
final class CheckController
{
    #[Route('/api/checks', methods: ['GET'])]
    #[ApiRequest(query: ListChecksFilterType::class)]
    #[ApiResponses([CheckResponse::class])]
    #[McpTool(description: 'List the checks.')]
    public function listChecks(): CheckResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/checks/verdicts', methods: ['POST'])]
    #[ApiRequest(body: RecordVerdictType::class)]
    #[ApiResponses([CheckResponse::class])]
    #[McpTool(description: 'Record a verdict.')]
    public function recordVerdict(): CheckResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
