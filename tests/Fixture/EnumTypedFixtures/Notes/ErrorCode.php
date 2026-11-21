<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes;

/** Sent as its value, as json_encode writes a backed enum that doesn't serialise itself. */
enum ErrorCode: string
{
    case Conflict = 'conflict';
    case Gone = 'gone';
}
