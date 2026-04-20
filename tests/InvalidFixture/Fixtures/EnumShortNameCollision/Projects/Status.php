<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\EnumShortNameCollision\Projects;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
