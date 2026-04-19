<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Controller;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Input\ProjectPathParams;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Response\ValidationErrorResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\CreateProjectRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\ProjectFiltersType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\UpdateProjectRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\CreateProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\DeleteProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ShowProjectResponse;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\UpdateProjectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController
{
    #[Route('/api/projects', methods: ['GET'])]
    #[ApiRequest(query: ProjectFiltersType::class)]
    #[ApiResponses([ShowProjectResponse::class, ValidationErrorResponse::class])]
    public function index(): ShowProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/projects/{id}', methods: ['GET'])]
    #[ApiRequest(path: ProjectPathParams::class)]
    #[ApiResponses([ShowProjectResponse::class, ValidationErrorResponse::class])]
    public function show(): ShowProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/projects', methods: ['POST'])]
    #[ApiRequest(body: CreateProjectRequestType::class)]
    #[ApiResponses([CreateProjectResponse::class, ValidationErrorResponse::class])]
    public function create(): CreateProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/projects/{id}', methods: ['PUT'])]
    #[ApiRequest(
        body: UpdateProjectRequestType::class,
        path: ProjectPathParams::class,
    )]
    #[ApiResponses([UpdateProjectResponse::class, ValidationErrorResponse::class])]
    public function update(): UpdateProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }

    #[Route('/api/projects/{id}', methods: ['DELETE'])]
    #[ApiResponses([DeleteProjectResponse::class])]
    public function delete(): DeleteProjectResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
