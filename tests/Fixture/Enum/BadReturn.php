<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Enum;

enum BadReturn: string
{
    case A = 'a';
    case B = 'b';

    public function code(): int
    {
        return self::A === $this ? 1 : 2;
    }
}
