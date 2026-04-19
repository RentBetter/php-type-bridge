<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Response\ValidationErrorResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ShowProjectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UndeclaredThrowController
{
    #[Route('/api/undeclared-throw', methods: ['GET'])]
    #[ApiResponses([ShowProjectResponse::class])]
    public function show(): ShowProjectResponse
    {
        throw new ValidationErrorResponse([]);
    }
}
