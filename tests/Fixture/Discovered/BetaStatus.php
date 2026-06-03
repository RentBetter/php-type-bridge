<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Discovered;

enum BetaStatus: string implements Marked
{
    case DRAFT = 'draft';
    case LIVE = 'live';
}
