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
    // No default: the property cannot represent its own absence, so a form field that
    // requires it is genuinely a required key.
    public string $title;
    public ?string $description = null;
    public ?ScaffoldStatus $status = null;
    public ?DateTimeImmutable $dueDate = null;
    public bool $archived = false;
    public int $order = 0;
    /** @var string[] */
    public array $tags = [];
}
