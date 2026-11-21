<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedClassTypedFixtures\Things;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** A base's property typed with a class that declares no shape. */
abstract class BaseStampResponse implements ApiResponse, HttpOk
{
    public \DateTimeImmutable $at;
}
