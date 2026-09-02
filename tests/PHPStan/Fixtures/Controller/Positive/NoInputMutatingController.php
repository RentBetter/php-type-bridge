<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Positive;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\CreateProjectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A mutating route with nothing to submit: the bare #[ApiRequest] is the declaration.
 */
final class NoInputMutatingController
{
    #[Route('/api/no-input', methods: ['POST'])]
    #[ApiRequest]
    #[ApiResponses([CreateProjectResponse::class])]
    public function refresh(): CreateProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
