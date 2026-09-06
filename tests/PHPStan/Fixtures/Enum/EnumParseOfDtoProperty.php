<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Enum;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
}

enum Colour
{
    case Red;
}

final class TaskFilterData
{
    /** @var list<string> */
    public array $status = [];
    public ?string $entityType = null;
    public ?TaskStatus $typed = null;
}

final class WebhookPayload
{
    public ?string $status = null;
}

final class TaskService
{
    /** @return list<TaskStatus> */
    public function badTryFrom(TaskFilterData $filter): array
    {
        $statuses = [];
        foreach ($filter->status as $value) {
            // ERROR - the DTO should have typed this as TaskStatus
            $parsed = TaskStatus::tryFrom($value);
            if (null !== $parsed) {
                $statuses[] = $parsed;
            }
        }

        return $statuses;
    }

    public function badFrom(TaskFilterData $filter): TaskStatus
    {
        // ERROR - from() is the same design mistake, it just throws instead of dropping
        return TaskStatus::from($filter->entityType ?? '');
    }

    public function alreadyTyped(TaskFilterData $filter): ?TaskStatus
    {
        // OK - nothing parsed, the DTO carries the enum
        return $filter->typed;
    }

    public function externalInput(WebhookPayload $payload): ?TaskStatus
    {
        // OK - a webhook body is genuinely external input, not a validated form DTO
        return TaskStatus::tryFrom($payload->status ?? '');
    }

    public function fromLocal(string $raw): ?TaskStatus
    {
        // OK - no form DTO in the signature at all
        return TaskStatus::tryFrom($raw);
    }

    public function pureEnum(): Colour
    {
        // OK - not a backed enum, so it has no tryFrom/from to misuse
        return Colour::Red;
    }
}
