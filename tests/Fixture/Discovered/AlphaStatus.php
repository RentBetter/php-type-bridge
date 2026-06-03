<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Discovered;

enum AlphaStatus: string implements Marked
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
