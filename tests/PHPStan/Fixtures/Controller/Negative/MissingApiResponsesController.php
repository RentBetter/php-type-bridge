<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative;

use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ShowProjectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MissingApiResponsesController
{
    #[Route('/api/missing-responses', methods: ['GET'])]
    public function index(): ShowProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
