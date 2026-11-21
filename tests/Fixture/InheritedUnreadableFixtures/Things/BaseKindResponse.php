<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedUnreadableFixtures\Things;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** A type PHPStan reads and the parser does not, on a base a response extends. */
abstract class BaseKindResponse implements ApiResponse, HttpOk
{
    public const array KINDS = ['a' => 1];

    /** @var key-of<self::KINDS> */
    public string $kind = 'a';
}
