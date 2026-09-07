<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Discovered;

/**
 * Int-backed on purpose: its ids are not derivable from its backing values, which is the
 * case `id-of<Enum>` exists for and the one `value-of<Enum>` cannot express at all.
 */
enum GammaStatus: int implements Marked
{
    case PENDING = 0;
    case SETTLED = 1;
}
