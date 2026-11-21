<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QualifiedEnumFixtures\Notes;

enum Status: string
{
    case Open = 'open';
    case Closed = 'closed';
}
