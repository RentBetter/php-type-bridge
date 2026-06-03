<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Enum;

enum Colour: string
{
    case RED = 'red';
    case GREEN = 'green';

    public function id(): string
    {
        return 'COLOUR_' . $this->name;
    }
}
