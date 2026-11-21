<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Utf8RoutedMcpFixtures\Codes\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use PTGS\TypeBridge\Tests\Fixture\Utf8RoutedMcpFixtures\Codes\Response\CodeResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CodeController
{
    #[Route('/codes/{code}', requirements: ['code' => '\w+'], methods: ['GET'], utf8: false)]
    #[ApiResponses([CodeResponse::class])]
    #[McpTool(description: 'A code.')]
    public function showCodeAction(): CodeResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
