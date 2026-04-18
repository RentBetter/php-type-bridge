<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

enum AdvancedState: string
{
    case Draft = 'draft';
    case Live = 'live';
}
