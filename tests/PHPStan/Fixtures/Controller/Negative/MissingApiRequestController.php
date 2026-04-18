<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\CreateProjectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MissingApiRequestController
{
    #[Route('/api/missing-request', methods: ['POST'])]
    #[ApiResponses([CreateProjectResponse::class])]
    public function create(): CreateProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
