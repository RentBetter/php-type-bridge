<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AliasFixtures\Projects\Controller;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\AliasFixtures\Common\Response\SharedResponse as CommonSharedResponse;
use PTGS\TypeBridge\Tests\Fixture\AliasFixtures\Projects\Response\SharedResponse as ProjectsSharedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SharedController
{
    #[Route('/api/shared', methods: ['GET'])]
    #[ApiResponses([CommonSharedResponse::class, ProjectsSharedResponse::class])]
    public function __invoke(): CommonSharedResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
