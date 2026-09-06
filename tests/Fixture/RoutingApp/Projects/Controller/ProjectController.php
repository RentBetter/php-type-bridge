<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RoutingApp\Projects\Controller;

use Symfony\Component\Routing\Attribute\Route;

/**
 * The prefix lives in routes.yaml, not here — the method attribute alone says
 * "/accounts/{accountId}/projects", while the application serves "/api/accounts/{accountId}/projects".
 */
final class ProjectController
{
    #[Route('/accounts/{accountId}/projects', name: 'project_list', methods: ['GET'])]
    public function listAction(): void {}

    #[Route('/accounts/{accountId}/projects', name: 'project_create', methods: ['POST'])]
    public function createAction(): void {}
}
