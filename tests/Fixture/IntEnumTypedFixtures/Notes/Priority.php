<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IntEnumTypedFixtures\Notes;

/** Sent as its value, an int. */
enum Priority: int
{
    case Low = 1;
    case High = 2;
}
