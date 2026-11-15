<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumResponseNameFixtures\Notes;

/** Sent as its value. */
enum Status: string
{
    case Open = 'open';
    case Closed = 'closed';
}
