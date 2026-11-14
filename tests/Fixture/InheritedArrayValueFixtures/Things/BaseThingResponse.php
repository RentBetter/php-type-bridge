<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedArrayValueFixtures\Things;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** `array<V>`, which PHPStan reads as `array<array-key, V>`, on a base a response extends. */
abstract class BaseThingResponse implements ApiResponse, HttpOk
{
    /** @var array<string> */
    public array $warnings = [];
}
