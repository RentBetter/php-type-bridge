<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

class Thing
{
    public function __construct(
        private readonly string $id = 'thing-1',
    ) {}

    public function getId(): string
    {
        return $this->id;
    }
}
