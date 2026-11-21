<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmitterEnumToolFixtures\Tasks;

/** Backed, but sent as the type its project's emitter publishes. */
enum TaskStatus: string implements Shaped
{
    case Open = 'open';
    case Closed = 'closed';
}
