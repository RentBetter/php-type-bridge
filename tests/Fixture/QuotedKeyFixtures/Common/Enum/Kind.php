<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QuotedKeyFixtures\Common\Enum;

enum Kind: string
{
    case Stage = 'stage';
    case Task = 'task';
}
