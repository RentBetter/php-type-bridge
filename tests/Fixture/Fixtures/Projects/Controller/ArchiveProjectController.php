<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Input\ProjectPathParams;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\UpdateProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ValidationErrorResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ArchiveProjectController
{
    #[Route('/api/projects/{id}/archive', methods: ['POST'])]
    #[ApiRequest(path: ProjectPathParams::class)]
    #[ApiResponses([UpdateProjectResponse::class, ValidationErrorResponse::class])]
    public function __invoke(): UpdateProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
