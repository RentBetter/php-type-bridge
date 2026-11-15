<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\WebInputFixtures\Web;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class WebController
{
    #[Route('/web', methods: ['GET'])]
    #[ApiRequest(query: N1Type::class)]
    #[ApiResponses([WebResponse::class])]
    public function listWebAction(): WebResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/web', methods: ['PUT'])]
    #[ApiRequest(body: N1Type::class)]
    #[ApiResponses([WebResponse::class])]
    #[McpTool(description: 'Save a web.')]
    public function saveWebAction(): WebResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
