<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumLiteralFixtures\Clock;

/** Values a TypeScript string literal escapes. */
enum Time: string
{
    case OClock = "o'clock";
    case Slash = 'a\\b';
}
