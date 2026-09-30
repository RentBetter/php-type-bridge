<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Catalog\Enum;

use PTGS\TypeBridge\Tests\Fixture\ClassRefEmitter\Shaped;

enum TaskStatus: string implements Shaped
{
    case OPEN = 'open';
    case DONE = 'done';
}
