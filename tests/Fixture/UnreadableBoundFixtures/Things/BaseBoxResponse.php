<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnreadableBoundFixtures\Things;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/**
 * A param bound by a type PHPStan reads and the parser does not, which a response leaves unbound.
 *
 * @template T of \Countable&\Traversable
 */
abstract class BaseBoxResponse implements ApiResponse, HttpOk
{
    /** @var T|null */
    public mixed $item = null;
}
