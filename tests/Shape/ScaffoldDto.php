<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Shape;

use DateTimeImmutable;

enum ScaffoldStatus: string
{
    case Todo = 'todo';
    case Done = 'done';
}

final class ScaffoldDto
{
    public string $title = '';
    public ?string $description = null;
    public ?ScaffoldStatus $status = null;
    public ?DateTimeImmutable $dueDate = null;
    public bool $archived = false;
    public int $order = 0;
    /** @var string[] */
    public array $tags = [];
}
