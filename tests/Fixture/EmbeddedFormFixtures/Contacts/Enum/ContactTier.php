<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Enum;

use PTGS\TypeBridge\Attribute\ValueOfName;

/** Its value union is named otherwise, and it declares no shape: sent as its value. */
#[ValueOfName('ContactTierId')]
enum ContactTier: string
{
    case Gold = 'gold';
    case Silver = 'silver';
}
