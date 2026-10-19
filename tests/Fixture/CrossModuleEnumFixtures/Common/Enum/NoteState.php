<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CrossModuleEnumFixtures\Common\Enum;

enum NoteState: string
{
    case Open = 'open';
    case Closed = 'closed';
}
