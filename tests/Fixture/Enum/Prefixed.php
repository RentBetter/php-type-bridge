<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Enum;

enum Prefixed: string
{
    public const string ID_PREFIX = 'PFX_';

    case ONE = 'one';
    case TWO = 'two';
}
