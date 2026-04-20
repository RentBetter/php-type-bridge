<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\EnumShortNameCollision\Accounts;

enum Status: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
