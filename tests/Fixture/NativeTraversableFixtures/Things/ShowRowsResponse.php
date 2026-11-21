<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NativeTraversableFixtures\Things;

use PTGS\TypeBridge\Status\HttpOk;
use Traversable;

/**
 * A Traversable that is no `iterable`: no array beside it.
 */
final class ShowRowsResponse implements HttpOk
{
    public Traversable|string $rows = '';
}
