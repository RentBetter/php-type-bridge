<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\CreateProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ShowProjectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UndeclaredReturnController
{
    #[Route('/api/undeclared-return', methods: ['GET'])]
    #[ApiResponses([CreateProjectResponse::class])]
    public function show(): ShowProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
