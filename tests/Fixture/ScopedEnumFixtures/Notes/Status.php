<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ScopedEnumFixtures\Notes;

enum Status: string
{
    case Open = 'open';
    case Shut = 'shut';
}
