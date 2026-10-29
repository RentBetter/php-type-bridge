<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumOnlyModuleFixtures\Common\Enum;

use PTGS\TypeBridge\Attribute\ValueOfName;

/** Names its value union as the other enum's is named. */
#[ValueOfName('NoteState')]
enum Status: string
{
    case Active = 'active';
}
