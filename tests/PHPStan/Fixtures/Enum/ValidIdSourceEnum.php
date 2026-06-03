<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Enum;

use PTGS\TypeBridge\Enum\EnumIdSource;

#[EnumIdSource(method: 'id')]
enum ValidIdSourceEnum: string
{
    case A = 'a';
    case B = 'b';

    public function id(): string
    {
        return 'VALID_' . $this->name;
    }
}
