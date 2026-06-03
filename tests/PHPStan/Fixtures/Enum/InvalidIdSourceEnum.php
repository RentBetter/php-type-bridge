<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Enum;

use PTGS\TypeBridge\Enum\EnumIdSource;

#[EnumIdSource(method: 'code')]
enum InvalidIdSourceEnum: string
{
    case A = 'a';

    public function code(): int
    {
        return 1;
    }
}
