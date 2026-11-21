<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmitterEnumToolFixtures\Tasks;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\McpTool;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController
{
    #[Route('/tasks', methods: ['POST'])]
    #[ApiRequest(body: TaskType::class)]
    #[ApiResponses([TaskResponse::class])]
    #[McpTool(description: 'Save a task.')]
    public function saveTaskAction(): TaskResponse
    {
        throw new \LogicException('Fixture only.');
    }
}
